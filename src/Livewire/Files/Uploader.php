<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Files;

use Base\Tenant\Files\FileCollection;
use Base\Tenant\Models\File;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * The drop zone.
 *
 * The bytes never pass through Livewire: the browser asks for a signature,
 * PUTs straight to the disk and then tells the application what landed. A
 * `wire:model` upload would push a 100 MB file through PHP's request handling
 * twice, and on Vapor it would not arrive at all -- Lambda caps a request body
 * at 6 MB.
 *
 * The component only renders the zone and reports what finished; the upload
 * itself lives in the Alpine block in the view.
 */
class Uploader extends Component
{
    public string $collection = 'library';

    public ?string $fileableType = null;

    public ?string $fileableId = null;

    /** Reset on every mount: it describes the batch in flight, not the model. */
    public bool $multiple = true;

    public function mount(?Model $fileable = null, string $collection = 'library', bool $multiple = true): void
    {
        $this->collection = $collection;
        $this->multiple = $multiple;

        if ($fileable) {
            $this->fileableType = $fileable->getMorphClass();
            $this->fileableId = (string) $fileable->getKey();
        }
    }

    /**
     * Called by the browser once `finalize` has returned.
     *
     * It carries no data of its own -- the id is looked up rather than
     * trusted -- because anything the browser sends here would be a claim
     * about a record it may not own.
     */
    public function uploaded(string $fileId): void
    {
        $file = File::query()->find($fileId);

        if (! $file) {
            return;
        }

        $this->dispatch('file-uploaded', fileId: $file->getKey(), collection: $file->collection);
    }

    public function render(): View
    {
        return view('base-tenant::livewire.files.uploader', [
            'rules' => $this->rules(),
        ]);
    }

    /**
     * What the browser is allowed to offer in the picker. Advisory: every one
     * of these is checked again on the server against the bytes that arrive.
     */
    protected function rules(): FileCollection
    {
        if ($this->fileableType && $this->fileableId) {
            $class = Model::getActualClassNameForMorph($this->fileableType);

            $model = class_exists($class)
                ? (new $class)->newQuery()->find($this->fileableId)
                : null;

            if ($model && method_exists($model, 'fileCollection')) {
                return $model->fileCollection($this->collection);
            }
        }

        $declared = config('base-tenant.files.collections', []);
        $rules = $declared[$this->collection] ?? [];

        return $rules instanceof FileCollection ? $rules : FileCollection::make(
            name: $this->collection,
            accepts: $rules['accepts'] ?? [],
            maxSize: $rules['max_size'] ?? null,
            single: $rules['single'] ?? false,
            variants: $rules['variants'] ?? [],
        );
    }
}
