<?php

declare(strict_types=1);

namespace Base\Tenant\Languages;

use Base\Tenant\Models\Language;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Which languages the application offers, right now.
 *
 *     Language::enabled();        // the picker's contents
 *     Language::enable('fr');     // live, no deploy
 *     Language::setDefault('es');
 *
 * Read on nearly every request, so the list is cached and the cache is dropped
 * by the four methods that can change it -- and by nothing else, because a
 * cache invalidated from more places than it is written from is a cache that
 * is eventually wrong.
 */
class LanguageManager
{
    public const CACHE_KEY = 'base-tenant:languages';

    /**
     * Every enabled language, in display order.
     *
     * @return Collection<int, Language>
     */
    public function enabled(): Collection
    {
        return $this->all()->where('enabled', true)->values();
    }

    /**
     * @return Collection<int, Language>
     */
    public function all(): Collection
    {
        return Cache::get(self::CACHE_KEY) ?? $this->load();
    }

    /**
     * Read the catalogue and cache it, unless the table is not there yet.
     *
     * The middleware asks for this on every request, so between installing the
     * package and running its migrations an unguarded query would turn every
     * page into a SQL error. The empty answer is deliberately not cached: the
     * moment the migration runs, the next request finds the real list.
     *
     * @return Collection<int, Language>
     */
    protected function load(): Collection
    {
        try {
            $languages = Language::query()->ordered()->get();
        } catch (QueryException) {
            return new Collection;
        }

        Cache::forever(self::CACHE_KEY, $languages);

        return $languages;
    }

    /**
     * The locale everything falls back to.
     *
     * Falls back to `app.locale` when the table has not been seeded, so a
     * fresh install answers something sensible instead of null -- the answer
     * feeds `setLocale()`, and null there breaks every translated string.
     */
    public function default(): string
    {
        return $this->all()->firstWhere('is_default', true)?->code
            ?? (string) config('app.locale', 'en');
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->enabled()->pluck('code')->all();
    }

    public function isEnabled(string $code): bool
    {
        return $this->enabled()->contains('code', $code);
    }

    /**
     * The locale to actually use for someone who asked for `$code`.
     *
     * A user whose language was switched off keeps a preference pointing at a
     * locale the application no longer offers. Returning it would show them a
     * half-translated interface; the default is the honest answer.
     */
    public function resolve(?string $code): string
    {
        return $code !== null && $this->isEnabled($code) ? $code : $this->default();
    }

    public function enable(string $code): Language
    {
        $language = $this->find($code);

        $language->update(['enabled' => true]);

        $this->flush();

        return $language->refresh();
    }

    /**
     * @throws RuntimeException when asked to switch off the default
     */
    public function disable(string $code): Language
    {
        $language = $this->find($code);

        // Switching off the default would leave the fallback pointing at a
        // language nobody can see, and every untranslated string with nowhere
        // to go. Promote another one first.
        if ($language->is_default) {
            throw new RuntimeException(
                "`{$code}` is the default language and cannot be disabled. Make another language the default first."
            );
        }

        $language->update(['enabled' => false]);

        $this->flush();

        return $language->refresh();
    }

    /**
     * Make one language the default, and only one.
     *
     * Both writes happen in a transaction: between clearing the old default
     * and setting the new one there is an instant with no default at all, and
     * a request landing there would find no fallback.
     */
    public function setDefault(string $code): Language
    {
        $language = $this->find($code);

        DB::transaction(function () use ($language): void {
            Language::query()->where('is_default', true)->update(['is_default' => false]);

            $language->update(['is_default' => true, 'enabled' => true]);
        });

        $this->flush();

        return $language->refresh();
    }

    public function reorder(string $code, int $position): Language
    {
        $language = $this->find($code);

        $language->update(['position' => $position]);

        $this->flush();

        return $language->refresh();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function find(string $code): Language
    {
        return Language::query()->where('code', $code)->first()
            ?? throw new RuntimeException("There is no `{$code}` language.");
    }
}
