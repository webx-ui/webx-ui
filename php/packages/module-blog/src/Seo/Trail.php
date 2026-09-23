<?php

declare(strict_types=1);

namespace WebxUi\Blog\Seo;

use Illuminate\Container\Container;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Contracts\Crumb;

/**
 * The step every trail of the blog starts with, after the site's home: the feed (§17.5 of the
 * SEO spec).
 *
 * Only when there is one. With an empty prefix the feed route is not registered at all — the
 * blog lives in the root of the site, and a step called "Blog" would lead nowhere.
 */
final class Trail
{
    public static function feed(string $locale): ?Crumb
    {
        $container = Container::getInstance();
        $prefix = UrlNormaliser::key((string) $container->make('config')->get('webx-blog.prefix', 'blog'));

        if ($prefix === '') {
            return null;
        }

        return new Crumb(
            (string) $container->make('translator')->get('webx-blog::blog.title', [], $locale),
            $container->make(SiteUrl::class)->to($prefix, $locale),
        );
    }

    /**
     * The feed, then whatever is given — without the steps that have nothing to say.
     *
     * @return list<Crumb>
     */
    public static function of(string $locale, ?Crumb ...$crumbs): array
    {
        return array_values(array_filter([self::feed($locale), ...$crumbs]));
    }
}
