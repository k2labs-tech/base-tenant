<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Files\FileStore;
use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Models\File;
use Base\Tenant\Support\Search;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every file the account holds, whatever it is attached to.
 *
 * The gallery answers "what is on this record"; this answers "what is this
 * account storing", which is the question asked when the storage bill arrives.
 */
#[Layout('base-tenant::layouts.app')]
class FileLibrary extends Component
{
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }
    use WithPagination;

    /** `image`, `document`, or empty for everything. */
    #[Url(except: '')]
    public string $filterKind = '';

    #[Url(except: '')]
    public string $filterCollection = '';

    public bool $showDeleteModal = false;

    public string $deletingId = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission('files.view'), 403);
    }

    public function render(): View
    {
        $files = $this->files();

        return view('base-tenant::livewire.file-library', [
            'files' => $files,
            'isEmptyTable' => $this->isEmptyResult($files),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'collections' => $this->collections(),
            'summary' => $this->summary(),
            'canDelete' => Auth::user()->hasPermission('files.delete'),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),
        ]);
    }

    /** @return array<int, string> */
    protected function sortableColumns(): array
    {
        return ['name', 'size', 'created_at'];
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterKind !== '' || $this->filterCollection !== '';
    }

    public function updatedFilterKind(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCollection(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['filterKind', 'filterCollection']);

        $this->resetTableFilters();
    }

    #[On('file-uploaded')]
    public function refresh(?string $fileId = null, ?string $collection = null): void
    {
        $this->resetPage();
    }

    public function confirmDelete(string $fileId): void
    {
        $this->deletingId = $fileId;
        $this->showDeleteModal = true;
    }

    /**
     * The file the modal is asking about. «Delete this file?» does not say
     * which of the twenty-five on screen is about to go.
     */
    public function deletingFile(): ?File
    {
        return $this->deletingId === '' ? null : File::query()->find($this->deletingId);
    }

    public function remove(FileStore $files): void
    {
        abort_unless(Auth::user()->hasPermission('files.delete'), 403);

        $file = $this->deletingFile();

        if (! $file) {
            return;
        }

        $files->delete($file);

        $this->reset(['deletingId', 'showDeleteModal']);

        Flux::toast(text: __('base-tenant::files.deleted'), variant: 'success');
    }

    /**
     * The header figures, counted over the whole library rather than over what
     * the filters leave visible.
     *
     * @return array<string, int>
     */
    protected function summary(): array
    {
        return [
            'total' => File::query()->count(),
            'bytes' => (int) File::query()->sum('size'),
        ];
    }

    /** @return array<int, string> */
    protected function collections(): array
    {
        return File::query()
            ->select('collection')
            ->distinct()
            ->orderBy('collection')
            ->pluck('collection')
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, File>
     */
    protected function files(): LengthAwarePaginator
    {
        $query = File::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                'name',
                Search::operator(),
                '%'.trim($this->search).'%'
            ))
            ->when($this->filterCollection !== '', fn (Builder $query) => $query->where(
                'collection',
                $this->filterCollection
            ))
            ->when($this->filterKind === 'image', fn (Builder $query) => $query->where(
                'mime_type',
                Search::operator(),
                'image/%'
            ))
            ->when($this->filterKind === 'document', fn (Builder $query) => $query->where(
                'mime_type',
                'not like',
                'image/%'
            ));

        return $this->applySort($query, 'created_at', 'desc')
            ->paginate($this->resolvedPerPage());
    }
}
