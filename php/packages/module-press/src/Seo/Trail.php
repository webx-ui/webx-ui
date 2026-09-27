<?php

declare(strict_types=1);

namespace WebxUi\Press\Seo;

use Illuminate\Container\Container;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;

/**
 * The first step of an outlet's trail, after the site's home: whatever stands at the prefix
 * (§4.7).
 *
 * The module has no index of its own (decision 12) — `/press` is a page made of blocks — so the
 * trail starts with what the registry holds there, told the way that entity tells it. Nothing
 * there, or nothing a reader may see, is no step at all, as with the recipes.
 */
final class Trail
{
    /** @return list<Crumb> */
    public static function index(string $locale): array
    {
        $prefix = UrlNormaliser::key((string) Container::getInstance()->make('config')->get('webx-press.prefix', 'press'));

        if ($prefix === '') {
            return [];
        }

        $route = Route::query()->canonical()->where('locale', $locale)->where('path', $prefix)->first();
        $entity = $route?->entity;

        if (! $entity instanceof HasBreadcrumbs || ($entity instanceof Visible && ! $entity->isVisible($locale))) {
            return [];
        }

        return $entity->breadcrumbs($locale);
    }

    /**
     * The page at the prefix, then whatever is given — without the steps that have nothing to say.
     *
     * @return list<Crumb>
     */
    public static function of(string $locale, ?Crumb ...$crumbs): array
    {
        return array_values(array_filter([...self::index($locale), ...$crumbs]));
    }
}
