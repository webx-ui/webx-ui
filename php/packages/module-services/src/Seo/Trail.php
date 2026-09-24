<?php

declare(strict_types=1);

namespace WebxUi\Services\Seo;

use Illuminate\Container\Container;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;

/**
 * The steps every trail of the services starts with, after the site's home: the index (§4.5).
 *
 * Only when there is one. With an empty prefix the index route is not registered — the services
 * live in the root of the site, and a step called "Services" would lead nowhere. With the index
 * switched off the prefix is somebody else's address — usually a page made of blocks — and the
 * trail starts with whatever the registry holds there, told the way that entity tells it.
 */
final class Trail
{
    /** @return list<Crumb> */
    public static function index(string $locale): array
    {
        $container = Container::getInstance();
        $config = $container->make('config');
        $prefix = UrlNormaliser::key((string) $config->get('webx-services.prefix', 'services'));

        if ($prefix === '') {
            return [];
        }

        if (! (bool) $config->get('webx-services.index', true)) {
            return self::standIn($prefix, $locale);
        }

        return [new Crumb(
            (string) $container->make('translator')->get('webx-services::services.title', [], $locale),
            $container->make(SiteUrl::class)->to($prefix, $locale),
        )];
    }

    /**
     * The index, then whatever is given — without the steps that have nothing to say.
     *
     * @return list<Crumb>
     */
    public static function of(string $locale, ?Crumb ...$crumbs): array
    {
        return array_values(array_filter([...self::index($locale), ...$crumbs]));
    }

    /**
     * What answers the prefix instead of the index. Nothing when nothing does, or when it is
     * hidden: a step that leads to a 404 is worse than a shorter trail.
     *
     * @return list<Crumb>
     */
    private static function standIn(string $prefix, string $locale): array
    {
        $route = Route::query()->canonical()->where('locale', $locale)->where('path', $prefix)->first();
        $entity = $route?->entity;

        if (! $entity instanceof HasBreadcrumbs || ($entity instanceof Visible && ! $entity->isVisible($locale))) {
            return [];
        }

        return $entity->breadcrumbs($locale);
    }
}
