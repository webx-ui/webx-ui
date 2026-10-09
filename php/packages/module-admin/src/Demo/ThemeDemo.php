<?php

declare(strict_types=1);

namespace WebxUi\Admin\Demo;

use Illuminate\Container\Container;
use WebxUi\Themes\ThemeChain;

/**
 * Where the site's theme keeps demo content for a module (§15.1 of the themes spec).
 *
 * The first layer from the top that has `demo/<module>/` speaks for the module; the module's own
 * fixtures fill in whatever that directory leaves out. Without `webx-ui/themes`, or on a site
 * with no theme, there is no such directory and every module seeds what it always did.
 */
final class ThemeDemo
{
    public static function directory(string $module): ?string
    {
        foreach (self::chain()?->layers ?? [] as $layer) {
            if (is_dir($layer->path.'/demo/'.$module)) {
                return $layer->path.'/demo/'.$module;
            }
        }

        return null;
    }

    /**
     * The site prints its pages through a theme. Its layout already has a header and a footer —
     * the regions' fallbacks — so a module's demo block in those regions would cover the very
     * thing the theme is there to show.
     */
    public static function themed(): bool
    {
        return (self::chain()?->layers ?? []) !== [];
    }

    private static function chain(): ?ThemeChain
    {
        $container = Container::getInstance();

        if (! class_exists(ThemeChain::class) || ! $container->bound(ThemeChain::class)) {
            return null;
        }

        return $container->make(ThemeChain::class);
    }
}
