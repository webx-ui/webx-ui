<?php

declare(strict_types=1);

namespace WebxUi\Services\Seo;

use Illuminate\Container\Container;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Contracts\Crumb;

/**
 * The step every trail of the services starts with, after the site's home: the index (§4.5).
 *
 * Only when there is one. With an empty prefix the index route is not registered — the services
 * live in the root of the site, and a step called "Services" would lead nowhere.
 */
final class Trail
{
    public static function index(string $locale): ?Crumb
    {
        $container = Container::getInstance();
        $prefix = UrlNormaliser::key((string) $container->make('config')->get('webx-services.prefix', 'services'));

        if ($prefix === '') {
            return null;
        }

        return new Crumb(
            (string) $container->make('translator')->get('webx-services::services.title', [], $locale),
            $container->make(SiteUrl::class)->to($prefix, $locale),
        );
    }

    /**
     * The index, then whatever is given — without the steps that have nothing to say.
     *
     * @return list<Crumb>
     */
    public static function of(string $locale, ?Crumb ...$crumbs): array
    {
        return array_values(array_filter([self::index($locale), ...$crumbs]));
    }
}
