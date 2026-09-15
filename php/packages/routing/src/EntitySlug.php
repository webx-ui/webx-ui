<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Database\Eloquent\Model;

/**
 * The one place that knows how to read and write an entity's slug.
 *
 * Two things make this less trivial than `$entity->slug`. A slug is usually translatable, so
 * the value depends on the language the address is being built for; and a suffix picked to
 * settle a collision has to go back into the entity (§2, decision 4) rather than only into the
 * registry, or the form and the site would show different addresses.
 *
 * A model says which attribute holds its slug through `routeSlugAttribute()` — `HasUrl`
 * provides it and defaults to `slug`.
 */
final class EntitySlug
{
    public static function attribute(Model $entity): string
    {
        return method_exists($entity, 'routeSlugAttribute') ? $entity->routeSlugAttribute() : 'slug';
    }

    public static function read(Model $entity, string $locale, ?string $attribute = null): string
    {
        $attribute ??= self::attribute($entity);

        if (self::isTranslatable($entity, $attribute)) {
            // Through a callable rather than `$entity->getTranslation(...)`: the method comes
            // from a trait in another package that this one does not require, so there is no
            // type to name — only the fact that the model has it.
            $read = [$entity, 'getTranslation'];

            return is_callable($read) ? (string) $read($attribute, $locale) : '';
        }

        return (string) $entity->getAttribute($attribute);
    }

    public static function write(Model $entity, string $locale, string $value, ?string $attribute = null): void
    {
        $attribute ??= self::attribute($entity);

        if (self::isTranslatable($entity, $attribute)) {
            $write = [$entity, 'setTranslation'];

            if (is_callable($write)) {
                $write($attribute, $locale, $value);
            }

            return;
        }

        $entity->setAttribute($attribute, $value);
    }

    private static function isTranslatable(Model $entity, string $attribute): bool
    {
        if (! method_exists($entity, 'translatable')) {
            return false;
        }

        /** @var list<string> $translatable */
        $translatable = $entity->translatable();

        return in_array($attribute, $translatable, true);
    }
}
