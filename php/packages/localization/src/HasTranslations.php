<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;

/**
 * Attributes that hold one value per language.
 *
 * The value lives in a JSON column as `{"en": "Contacts", "uk": "Контакти"}`, which is the
 * shape `spatie/laravel-translatable` uses too — data written here can be read by anything
 * that understands that convention, and moving away later is a matter of reading the column.
 *
 * A model names the attributes and otherwise goes on being a model:
 *
 *     class Page extends Model
 *     {
 *         use HasTranslations;
 *
 *         public function translatable(): array
 *         {
 *             return ['title', 'slug', 'content'];
 *         }
 *     }
 *
 *     $page->title;                      // the language of the current request, with fallback
 *     $page->title = 'Contacts';         // writes that language, leaves the others alone
 *     $page->getTranslation('title', 'uk');
 *     $page->setTranslations('title', ['en' => 'Contacts', 'uk' => 'Контакти']);
 *
 * What this deliberately does not do is make a slug unique inside a language: a JSON column
 * cannot carry that index. Uniqueness of an address belongs to the routing table in
 * `webx-ui/seo`, where it holds across every kind of entity at once rather than one table at
 * a time — `whereTranslation()` below is what validation uses until then.
 */
trait HasTranslations
{
    /** Set by `forLocale()`; null means "whatever language the request is in". */
    protected ?string $translationLocale = null;

    /**
     * The attributes stored as a language map. Override in the model.
     *
     * @return list<string>
     */
    public function translatable(): array
    {
        return [];
    }

    /**
     * Eloquent has to know these columns are JSON, or saving one writes the word "Array".
     */
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(array_fill_keys($this->translatable(), 'array'));
    }

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->translatable(), true);
    }

    /**
     * @param  string  $key
     */
    public function getAttributeValue($key): mixed
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->getTranslation($key, $this->translationLocale());
    }

    /**
     * @param  string  $key
     */
    public function setAttribute($key, $value): mixed
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::setAttribute($key, $value);
        }

        // An array is the whole map — that is how a form that edits every language at once
        // arrives, and how `create()` is written in a seeder.
        return is_array($value)
            ? $this->setTranslations($key, $value)
            : $this->setTranslation($key, $this->translationLocale(), $value);
    }

    /**
     * The value in one language, falling back through the chain unless told not to.
     */
    public function getTranslation(string $key, ?string $locale = null, bool $fallback = true): mixed
    {
        $translations = $this->getTranslations($key);
        $locale = LocaleCatalogue::normalise($locale ?? $this->translationLocale());

        if ($this->isPresent($translations[$locale] ?? null)) {
            return $translations[$locale];
        }

        if (! $fallback) {
            return null;
        }

        foreach ($this->translationChain($locale) as $candidate) {
            if ($this->isPresent($translations[$candidate] ?? null)) {
                return $translations[$candidate];
            }
        }

        return null;
    }

    public function hasTranslation(string $key, ?string $locale = null): bool
    {
        return $this->getTranslation($key, $locale, false) !== null;
    }

    /**
     * Every language a value has been written in.
     *
     * @return array<string, mixed>
     */
    public function getTranslations(string $key): array
    {
        $raw = $this->attributes[$key] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // A column that used to hold a single language and has not been converted. Reading it
        // as the default language's value is better than reading it as nothing: the site keeps
        // working, and the next save writes a proper map.
        return [$this->defaultTranslationLocale() => $raw];
    }

    /**
     * Every translatable attribute as its full map — what the panel edits.
     *
     * @return array<string, array<string, mixed>>
     */
    public function translationsToArray(): array
    {
        $translations = [];

        foreach ($this->translatable() as $key) {
            $translations[$key] = $this->getTranslations($key);
        }

        return $translations;
    }

    public function setTranslation(string $key, ?string $locale, mixed $value): static
    {
        $locale = LocaleCatalogue::normalise($locale ?? $this->translationLocale());
        $translations = $this->getTranslations($key);

        if ($value === null) {
            unset($translations[$locale]);
        } else {
            $translations[$locale] = $value;
        }

        return $this->setTranslations($key, $translations);
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    public function setTranslations(string $key, array $translations): static
    {
        $normalised = [];

        foreach ($translations as $locale => $value) {
            if ($value === null) {
                continue;
            }

            $normalised[LocaleCatalogue::normalise((string) $locale)] = $value;
        }

        parent::setAttribute($key, $normalised);

        return $this;
    }

    public function forgetTranslation(string $key, string $locale): static
    {
        return $this->setTranslation($key, $locale, null);
    }

    /**
     * Read this record as though the request were in another language.
     *
     * A copy rather than a switch, so handing it to a view cannot change the language of the
     * record somebody else is holding.
     */
    public function forLocale(string $locale): static
    {
        $copy = clone $this;
        $copy->translationLocale = LocaleCatalogue::normalise($locale);

        return $copy;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereTranslation(
        Builder $query,
        string $key,
        mixed $value,
        ?string $locale = null,
    ): Builder {
        return $query->where($this->translationPath($key, $locale), $value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereTranslationLike(
        Builder $query,
        string $key,
        string $value,
        ?string $locale = null,
    ): Builder {
        return $query->where($this->translationPath($key, $locale), 'like', $value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrderByTranslation(
        Builder $query,
        string $key,
        string $direction = 'asc',
        ?string $locale = null,
    ): Builder {
        return $query->orderBy($this->translationPath($key, $locale), $direction);
    }

    /**
     * The resolved value, not the map: a public page renders one language, and an API for the
     * site should not hand out every translation of everything. The panel asks for the maps
     * explicitly with `translationsToArray()`.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach ($this->translatable() as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = $this->getTranslation($key);
            }
        }

        return $attributes;
    }

    protected function translationLocale(): string
    {
        return $this->translationLocale ?? $this->locales()?->current() ?? 'en';
    }

    protected function defaultTranslationLocale(): string
    {
        return $this->locales()?->defaultCode() ?? 'en';
    }

    /**
     * @return list<string>
     */
    protected function translationChain(string $locale): array
    {
        $locales = $this->locales();

        return $locales === null ? [$locale] : $locales->chain($locale);
    }

    private function translationPath(string $key, ?string $locale): string
    {
        return $key.'->'.LocaleCatalogue::normalise($locale ?? $this->translationLocale());
    }

    private function isPresent(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * The service is resolved rather than injected because a model is constructed in places
     * that have no container — `Model::hydrate()`, a queued job being unserialised.
     */
    private function locales(): ?Locales
    {
        $container = Container::getInstance();

        if (! $container->bound(Locales::class)) {
            return null;
        }

        $locales = $container->make(Locales::class);

        return $locales instanceof Locales ? $locales : null;
    }
}
