<?php

declare(strict_types=1);

namespace WebxUi\Events\Seo;

use Illuminate\Container\Container;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;

/**
 * The first step of every events trail, after the site's home: what stands at the prefix (§4.7).
 *
 * The index when it is on. When it is off the address belongs to somebody else — usually a page
 * made of blocks with the events on it (decision 12) — and the trail starts with whatever the
 * registry holds there, told the way that entity tells it. Nothing there, or nothing a reader may
 * see, is no step at all.
 */
final class Trail
{
    /** @return list<Crumb> */
    public static function index(string $locale): array
    {
        $container = Container::getInstance();
        $config = $container->make('config');
        $prefix = UrlNormaliser::key((string) $config->get('webx-events.prefix', 'events'));

        if ($prefix === '') {
            return [];
        }

        if (! (bool) $config->get('webx-events.index', true)) {
            return self::standIn($prefix, $locale);
        }

        return [new Crumb(
            (string) $container->make('translator')->get('webx-events::site.title', [], $locale),
            $container->make(SiteUrl::class)->to($prefix, $locale),
        )];
    }

    /**
     * Where "all events" leads: the last step of {@see index()}, or null when nothing answers.
     */
    public static function indexUrl(string $locale): ?string
    {
        $steps = self::index($locale);
        $last = end($steps);

        return $last instanceof Crumb ? $last->url : null;
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
