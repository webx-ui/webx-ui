<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;

/**
 * The address a panel list shows for a record: in the language the list is read in, and when the
 * record has none there, its address in the site's main language — with that language named, so
 * the row can say so in a tooltip instead of in its place.
 *
 * A row that said «no address in this language» where the page plainly opens on the site read as
 * a fault to whoever was not counting languages; one address and a quiet note reads as what it is.
 */
final class PanelAddress
{
    /**
     * @param  callable(string): ?Route  $canonical  The record's canonical row in a language — out
     *                                               of what the list loaded, so a list stays one query.
     * @return array{0: ?Route, 1: string, 2: ?string} The row, the language it is in, and that
     *                                                 language again when it is the fallback rather
     *                                                 than the one asked for.
     */
    public static function pick(callable $canonical, string $locale): array
    {
        $route = $canonical($locale);

        if ($route !== null) {
            return [$route, $locale, null];
        }

        $main = app(Locales::class)->defaultCode();

        if ($main === $locale) {
            return [null, $locale, null];
        }

        $route = $canonical($main);

        return $route === null ? [null, $locale, null] : [$route, $main, $main];
    }

    /**
     * The address a record will answer at once its draft is published, when that is not the one
     * it answers at now — or null when publishing moves nothing.
     *
     * What the publish question names. A slug renamed in the draft moves the address only on
     * publishing, so the row's `path` is still the old one; a question built from it promised
     * visitors the address that was about to become a redirect.
     *
     * Duck-typed on the draft and the address because neither is this package's: a record with
     * no draft, or a draft that leaves the slug alone, costs nothing — a list of a few hundred
     * rows computes no path at all.
     */
    public static function afterPublishing(Model $entity, string $locale, ?string $current): ?string
    {
        if (! method_exists($entity, 'hasDraft') || ! method_exists($entity, 'withDraft') || ! $entity->hasDraft()) {
            return null;
        }

        $draft = $entity->withDraft();

        if (! $draft instanceof Model || ! method_exists($draft, 'routePath')) {
            return null;
        }

        $slug = EntitySlug::read($draft, $locale);

        // An emptied slug is not an address; what publishing does with it is the form's to say.
        if ($slug === '' || $slug === EntitySlug::read($entity, $locale)) {
            return null;
        }

        $path = (string) $draft->routePath($locale);

        return $path === $current ? null : $path;
    }
}
