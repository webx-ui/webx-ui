<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;
use WebxUi\Localization\Models\Locale;

/**
 * The languages this site is published in, and the one it is speaking right now.
 *
 * Everything that needs to know about languages asks here rather than reading the table or the
 * config, because the answer comes from whichever of the two is available: the table once the
 * site is installed, the config before it is. A `migrate` on an empty database goes through
 * code that wants a locale long before `locales` exists, and that must not be a fatal error.
 */
class Locales
{
    /** @var Collection<int, Locale>|null */
    private ?Collection $loaded = null;

    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
        private readonly Cache $cache,
    ) {}

    /**
     * Every active language, in the order a site chose to show them.
     *
     * @return Collection<int, Locale>
     */
    public function all(): Collection
    {
        return $this->loaded ??= Locale::hydrate($this->rows());
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->all()->pluck('code')->all();
    }

    /**
     * Whether a stored value is a language map — `{ en: …, de: … }` — rather than one value.
     *
     * By its shape first: a list is never one (tags, a repeater's rows). A field whose
     * `localized` flag was switched after content was written holds the other shape, and a
     * reader that trusted the flag read a list of tags as a map of languages and lost it.
     *
     * Under a field that says it is localized, any map is one (an empty one too) — a language the
     * site has since dropped is still a language. Anywhere else a map is one only when every key
     * is a language — this site's, or one the catalogue knows — which a link or a picture, whose
     * keys are words like `url` and `path`, never is.
     */
    public function isMap(mixed $value, bool $localized = false): bool
    {
        if (! is_array($value)) {
            return false;
        }

        if ($localized) {
            return $value === [] || ! array_is_list($value);
        }

        if ($value === [] || array_is_list($value)) {
            return false;
        }

        $codes = $this->codes();

        foreach (array_keys($value) as $key) {
            if (! in_array((string) $key, $codes, true) && ! LocaleCatalogue::has((string) $key)) {
                return false;
            }
        }

        return true;
    }

    public function has(string $code): bool
    {
        return in_array(LocaleCatalogue::normalise($code), $this->codes(), true);
    }

    public function find(string $code): ?Locale
    {
        $code = LocaleCatalogue::normalise($code);

        return $this->all()->first(fn (Locale $locale): bool => $locale->code === $code);
    }

    public function default(): Locale
    {
        $locales = $this->all();

        return $locales->first(fn (Locale $locale): bool => $locale->is_default)
            ?? $locales->first()
            ?? Locale::fromCode($this->fallback(), ['is_default' => true]);
    }

    public function defaultCode(): string
    {
        return $this->default()->code;
    }

    public function fallback(): string
    {
        return LocaleCatalogue::normalise((string) $this->config->get('webx-localization.fallback', 'en'));
    }

    /** The language the current request is being answered in. */
    public function current(): string
    {
        return LocaleCatalogue::normalise($this->app->getLocale());
    }

    /**
     * The language of the site's content a panel request works in: the one asked for, the
     * request's, or the site's main one — whichever the site has first.
     *
     * Not the request's language for its own sake: in the panel that is the interface's, and a
     * panel read in Russian over an English-only site found no Russian page behind anything.
     */
    public function content(?string $asked = null): string
    {
        foreach ([$asked ?? '', $this->current()] as $code) {
            if ($code !== '' && $this->has($code)) {
                return $code;
            }
        }

        return $this->defaultCode();
    }

    /**
     * Switch the application over, if the language is one this site has.
     *
     * Returns whether it took: a caller resolving an unknown language from a URL wants to know
     * that it was refused rather than silently ignored.
     */
    public function use(string $code): bool
    {
        $code = LocaleCatalogue::normalise($code);

        if (! $this->has($code)) {
            return false;
        }

        $this->app->setLocale($code);

        return true;
    }

    /**
     * Where to look for a translation, in order, when the language asked for has none.
     *
     * @return list<string>
     */
    public function chain(?string $code = null): array
    {
        $chain = [
            LocaleCatalogue::normalise($code ?? $this->current()),
            $this->defaultCode(),
            $this->fallback(),
        ];

        return array_values(array_unique(array_filter($chain)));
    }

    /**
     * The languages the interface itself may be shown in — the config list, narrowed to what
     * the catalogue can name.
     *
     * @return list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>
     */
    public function panel(): array
    {
        /** @var list<string> $configured */
        $configured = (array) $this->config->get('webx-localization.panel', ['en']);
        $fallback = $this->fallback();

        $codes = array_values(array_unique(array_map(
            LocaleCatalogue::normalise(...),
            $configured === [] ? [$fallback] : $configured,
        )));

        return array_map(function (string $code) use ($fallback): array {
            $described = LocaleCatalogue::describe($code);

            return [
                'code' => $code,
                'name' => $described['name'],
                'nativeName' => $described['native'],
                'direction' => $described['direction'],
                'default' => $code === $fallback,
            ];
        }, $codes);
    }

    /**
     * The panel language to draw for somebody who has not chosen one.
     *
     * Their own preference wins where they have expressed one; otherwise the fallback, because
     * that is the translation we can be sure is complete.
     */
    public function panelDefault(): string
    {
        $codes = array_column($this->panel(), 'code');
        $fallback = $this->fallback();

        return in_array($fallback, $codes, true) ? $fallback : (string) ($codes[0] ?? 'en');
    }

    /** Narrow an arbitrary request — a header, a stored preference — to a language we have. */
    public function resolvePanel(?string $wanted): string
    {
        if ($wanted === null || $wanted === '') {
            return $this->panelDefault();
        }

        $codes = array_column($this->panel(), 'code');
        $wanted = LocaleCatalogue::normalise($wanted);

        if (in_array($wanted, $codes, true)) {
            return $wanted;
        }

        // `de-AT` should land on `de` rather than on English.
        $language = explode('-', $wanted)[0];

        return in_array($language, $codes, true) ? $language : $this->panelDefault();
    }

    /**
     * @return list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>
     */
    public function toPayload(): array
    {
        return $this->all()->map(fn (Locale $locale): array => $locale->toPayload())->values()->all();
    }

    public function forget(): void
    {
        $this->loaded = null;
        $this->cache->forget($this->cacheKey());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        if (! $this->caching()) {
            return $this->read();
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->remember(
            $this->cacheKey(),
            (int) $this->config->get('webx-localization.cache.ttl', 86400),
            fn (): array => $this->read(),
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function read(): array
    {
        try {
            $rows = Locale::query()->active()->ordered()->get()
                ->map(fn (Locale $locale): array => $locale->getAttributes())
                ->values()
                ->all();
        } catch (Throwable) {
            // No table yet: an install, a fresh test database, a `migrate` that has not run.
            // The configured seed is a complete answer for all of them.
            $rows = [];
        }

        return $rows === [] ? $this->seeded() : $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function seeded(): array
    {
        /** @var list<array<string, mixed>|string> $configured */
        $configured = (array) $this->config->get('webx-localization.locales', []);

        if ($configured === []) {
            $configured = [['code' => $this->fallback(), 'default' => true]];
        }

        $rows = [];
        $sort = 0;

        foreach ($configured as $entry) {
            $entry = is_string($entry) ? ['code' => $entry] : $entry;
            $code = LocaleCatalogue::normalise((string) ($entry['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $described = LocaleCatalogue::describe($code);

            $rows[] = [
                'code' => $code,
                'name' => (string) ($entry['name'] ?? $described['name']),
                'native_name' => (string) ($entry['native_name'] ?? $described['native']),
                'direction' => (string) ($entry['direction'] ?? $described['direction']),
                'is_default' => (bool) ($entry['default'] ?? false),
                'is_active' => true,
                'sort' => $sort++,
            ];
        }

        if ($rows !== [] && ! array_filter(array_column($rows, 'is_default'))) {
            $rows[0]['is_default'] = true;
        }

        return $rows;
    }

    /**
     * Write the configured languages into the table, for an installation that has just been
     * migrated. Anything already there is left alone — this seeds, it does not reset.
     */
    public function seed(): int
    {
        $created = 0;

        foreach ($this->seeded() as $row) {
            /** @var string $code */
            $code = $row['code'];

            if (Locale::query()->where('code', $code)->exists()) {
                continue;
            }

            Locale::query()->create($row);
            $created++;
        }

        $this->forget();

        return $created;
    }

    private function caching(): bool
    {
        return (bool) $this->config->get('webx-localization.cache.enabled', true)
            // Caching a list read inside a transaction that may still roll back is how a test
            // suite starts answering with rows that no longer exist.
            && DB::transactionLevel() === 0;
    }

    private function cacheKey(): string
    {
        return (string) $this->config->get('webx-localization.cache.prefix', 'webx.localization').'.locales';
    }
}
