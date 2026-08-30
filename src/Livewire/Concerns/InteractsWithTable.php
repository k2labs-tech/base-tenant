<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Estado compartido de las pantallas tabulares: búsqueda, orden y tamaño de
 * página, todo en la URL para que sobreviva a un refresco y se pueda compartir
 * por enlace.
 *
 * No toca la consulta: cada componente sigue siendo dueño de la suya y decide
 * qué columnas admite ordenar.
 */
trait InteractsWithTable
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?string $sortBy = null;

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    #[Url(except: 25)]
    public int $perPage = 25;

    /**
     * Columnas que este componente admite ordenar.
     *
     * Es un acuerdo, no una barrera: nada impide a un componente leer `$sortBy`
     * por su cuenta en vez de pasar por `applySort()`, y de hecho la vista lo
     * lee para saber qué cabecera lleva la flecha. La garantía real vive en los
     * tests de cada pantalla, que comprueban que la consulta ignora una columna
     * no declarada; sin ese test, aquí no hay nada que lo asegure.
     *
     * @return array<int, string>
     */
    abstract protected function sortableColumns(): array;

    public function sort(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'sortBy', 'sortDirection']);
        $this->resetPage();
    }

    /** Cualquier cambio de criterio invalida la página en la que estabas. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Aplica el orden pedido, si es uno de los declarados, y deja que el
     * componente ponga el suyo por defecto cuando no hay ninguno.
     *
     * `$defaultDirection` lleva valor por defecto para no tocar las llamadas ya
     * escritas: solo lo pasa quien necesita otra cosa, como el registro de
     * actividad, que se lee de lo más nuevo a lo más viejo.
     *
     * Esta es la única puerta por la que `sortBy` llega a la consulta: el valor
     * puede entrar por la URL sin pasar por `sort()`, así que la lista blanca se
     * vuelve a comprobar aquí y no en el punto de entrada.
     *
     * No se trata de inyección SQL: `orderBy()` entrecomilla el identificador,
     * así que un valor con sintaxis dentro no se ejecuta, se convierte en un
     * nombre de columna absurdo. Lo que evita la lista blanca es ordenar por
     * columnas que nadie quiso exponer (`?sortBy=password` filtra el orden
     * relativo de los hashes) y un error 500 en el motor de producción, que
     * SQLite no reproduce: acepta el identificador desconocido como literal.
     */
    protected function applySort(Builder $query, string $default, string $defaultDirection = 'asc'): Builder
    {
        if ($this->sortBy === null || ! in_array($this->sortBy, $this->sortableColumns(), true)) {
            return $query->orderBy($default, $defaultDirection === 'desc' ? 'desc' : 'asc');
        }

        return $query->orderBy($this->sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc');
    }

    /**
     * La versión en memoria de `applySort()`, para las pantallas cuyo listado
     * no sale de una consulta. Misma lista blanca, por el mismo motivo:
     * `sortBy` sigue llegando de la URL lo pinte quien lo pinte.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function applySortToCollection(Collection $items, string $default, string $defaultDirection = 'asc'): Collection
    {
        $requested = $this->sortBy !== null && in_array($this->sortBy, $this->sortableColumns(), true);

        $column = $requested ? $this->sortBy : $default;
        $direction = $requested ? $this->sortDirection : $defaultDirection;

        $sorted = $items->sortBy($column);

        return $direction === 'desc'
            ? $sorted->reverse()->values()
            : $sorted->values();
    }

    /**
     * Si la tabla está vacía de verdad, y no es que te hayas pasado de página.
     *
     * `isEmpty()` no distingue las dos cosas: con `?page=5` sobre una sola fila
     * la colección viene vacía y la pantalla anunciaba «aún no hay nada» encima
     * de datos que sí existen. Se llega por URL, que es justo lo que estas
     * pantallas comparten por enlace.
     */
    protected function isEmptyResult(LengthAwarePaginator $result): bool
    {
        return $result->total() === 0;
    }

    /** Tamaños que ofrece el selector, y los únicos que se aceptan. */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * El tamaño de página también llega del usuario. Se admite solo la lista
     * que ofrece el selector, no un rango: con un rango, `?perPage=97` pasaría
     * y los valores aceptados dejarían de coincidir con los ofrecidos.
     */
    protected function resolvedPerPage(): int
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            return 25;
        }

        return $this->perPage;
    }

    /** Densidades que ofrece el conmutador, y las únicas que se aceptan. */
    public const DENSITIES = ['comfortable', 'compact'];

    /**
     * Cuántas filas caben en pantalla es una preferencia de uso, no de estilo:
     * quien revisa una lista larga a diario la quiere compacta. Va en la URL
     * como el resto del estado de la tabla, así que también llega del usuario
     * y se valida igual.
     */
    #[Url(except: 'comfortable')]
    public string $density = 'comfortable';

    protected function resolvedDensity(): string
    {
        return in_array($this->density, self::DENSITIES, true) ? $this->density : 'comfortable';
    }

    /** Alto de fila y espaciado de celda, listos para la vista. */
    protected function densityClasses(): string
    {
        return $this->resolvedDensity() === 'compact'
            ? 'py-1.5 text-sm'
            : 'py-3';
    }
}
