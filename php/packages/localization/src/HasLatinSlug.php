<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A slug is ASCII and lower case, whoever wrote it: Russian «колодки» is `kolodki`, `Foo` is `foo`.
 *
 * On the model, because that is the one thing the panel, the exchange, an agent and a plain
 * `save()` all pass through: a check in a form would leave the other three doors open. `HasUrl`
 * brings it along, so a model with an address needs nothing; a model whose slug is no address —
 * a filter's key — uses it by name.
 *
 * Only a slug being written is touched. One that came out of the table unchanged keeps its
 * spelling however it is spelled, because a save of a price is no reason to move an address:
 * those are moved on purpose, with their redirects, by `webx:slugs:latin`.
 *
 * A slug that leaves nothing becomes `<type>-<id>`. A new record has no id yet, so it goes in
 * under a stand-in and takes the real one right after the insert.
 *
 * @mixin Model
 */
trait HasLatinSlug
{
    /** @var array<int|string, true> the languages waiting for an id; `0` — a slug with no languages */
    private array $latinSlugWaiting = [];

    public static function bootHasLatinSlug(): void
    {
        // Booted before the model's own `booted()`, so its checks see the slug as it will be.
        static::saving(static function (self $entity): void {
            if ($entity->isDirty($entity->latinSlugAttribute())) {
                $entity->latinizeSlug();
            }
        });

        static::created(static function (self $entity): void {
            $entity->settleLatinSlug();
        });
    }

    /** Which attribute holds the slug. */
    public function latinSlugAttribute(): string
    {
        return method_exists($this, 'routeSlugAttribute') ? $this->routeSlugAttribute() : 'slug';
    }

    /** What stands before the id when a slug leaves nothing: `product`, `event-category`. */
    public function latinSlugFallback(): string
    {
        return Str::kebab(class_basename($this));
    }

    /**
     * Brings every language of the slug to ASCII, changing the attribute and saving nothing.
     *
     * An empty value stays empty: that is a language with no address, not a slug that failed.
     */
    public function latinizeSlug(): void
    {
        $attribute = $this->latinSlugAttribute();

        if (! $this->latinSlugTranslated($attribute)) {
            $value = $this->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                // The language is asked for only when it matters: it is a read of the `locales` table.
                $locale = Slug::isLatin($value) ? 'en' : Container::getInstance()->make(Locales::class)->defaultCode();

                $this->setAttribute($attribute, $this->latinSlugOf($value, $locale, 0));
            }

            return;
        }

        $slugs = $this->latinSlugRead($attribute);
        $latin = $slugs;

        foreach ($slugs as $locale => $slug) {
            if (is_string($slug) && trim($slug) !== '') {
                $latin[$locale] = $this->latinSlugOf($slug, (string) $locale, $locale);
            }
        }

        if ($latin !== $slugs) {
            $this->latinSlugWrite($attribute, $latin);
        }
    }

    /**
     * The insert has happened: the stand-ins give way to `<type>-<id>`.
     *
     * Quietly, as a suffix goes back into the entity: this is the same save, not a second one.
     */
    public function settleLatinSlug(): void
    {
        if ($this->latinSlugWaiting === []) {
            return;
        }

        $attribute = $this->latinSlugAttribute();
        $slug = $this->latinSlugFallback().'-'.$this->getKey();

        if ($this->latinSlugTranslated($attribute)) {
            $slugs = $this->latinSlugRead($attribute);

            foreach (array_keys($this->latinSlugWaiting) as $locale) {
                $slugs[$locale] = $slug;
            }

            $this->latinSlugWrite($attribute, $slugs);
        } else {
            $this->setAttribute($attribute, $slug);
        }

        $this->latinSlugWaiting = [];
        $this->saveQuietly();
    }

    private function latinSlugOf(string $slug, string $locale, int|string $key): string
    {
        $latin = Slug::latin($slug, $locale);

        if ($latin !== '') {
            return $latin;
        }

        if ($this->exists) {
            return $this->latinSlugFallback().'-'.$this->getKey();
        }

        $this->latinSlugWaiting[$key] = true;

        return $this->latinSlugFallback().'-new-'.bin2hex(random_bytes(6));
    }

    /**
     * Through a callable: `HasTranslations` is a neighbour's choice, and a model with a plain slug
     * column has neither of these methods — which the branch above has already asked.
     *
     * @return array<array-key, mixed>
     */
    private function latinSlugRead(string $attribute): array
    {
        $read = [$this, 'getTranslations'];

        return is_callable($read) ? (array) $read($attribute) : [];
    }

    /**
     * @param  array<array-key, mixed>  $slugs
     */
    private function latinSlugWrite(string $attribute, array $slugs): void
    {
        $write = [$this, 'setTranslations'];

        if (is_callable($write)) {
            $write($attribute, $slugs);
        }
    }

    private function latinSlugTranslated(string $attribute): bool
    {
        return method_exists($this, 'isTranslatableAttribute') && $this->isTranslatableAttribute($attribute);
    }
}
