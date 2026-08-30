<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Files;

use Base\Tenant\Files\FileStore;
use Base\Tenant\Models\File;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The files already attached to a record, as a grid.
 *
 * Pairs with the uploader: it listens for what the uploader finishes rather
 * than polling, so a file appears the moment it exists.
 */
class Gallery extends Component
{
    public string $collection = 'library';

    public ?string $fileableType = null;

    public ?string $fileableId = null;

    public bool $canDelete = true;

    public bool $showDeleteModal = false;

    public string $deletingId = '';

    public function mount(?Model $fileable = null, string $collection = 'library', bool $canDelete = true): void
    {
        $this->collection = $collection;
        $this->canDelete = $canDelete;

        if ($fileable) {
            $this->fileableType = $fileable->getMorphClass();
            $this->fileableId = (string) $fileable->getKey();
        }
    }

    #[On('file-uploaded')]
    public function refresh(?string $fileId = null, ?string $collection = null): void
    {
        // Nothing to do beyond re-rendering: the query below runs again and
        // picks the new row up. The listener exists to make that happen.
    }

    public function confirmDelete(string $fileId): void
    {
        $this->deletingId = $fileId;
        $this->showDeleteModal = true;
    }

    /**
     * The file the modal is asking about, so the question can name it.
     */
    public function deletingFile(): ?File
    {
        return $this->deletingId === ''
            ? null
            : $this->files()->firstWhere('id', $this->deletingId);
    }

    public function remove(FileStore $files): void
    {
        $file = $this->deletingFile();

        if (! $file || ! $this->canDelete) {
            return;
        }

        $files->delete($file);

        $this->reset(['deletingId', 'showDeleteModal']);
    }

    public function render(): View
    {
        return view('base-tenant::livewire.files.gallery', [
            'files' => $this->files(),
        ]);
    }

    /**
     * @return Collection<int, File>
     */
    protected function files(): Collection
    {
        // The tenant scope is already on the model, so this cannot reach
        // another account's files even if the ids were guessed.
        return File::query()
            ->inCollection($this->collection)
            ->when(
                $this->fileableType && $this->fileableId,
                fn ($query) => $query
                    ->where('fileable_type', $this->fileableType)
                    ->where('fileable_id', $this->fileableId),
                fn ($query) => $query->whereNull('fileable_id'),
            )
            ->ordered()
            ->get();
    }
}
