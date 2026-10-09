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
        $container = Container::getInstance();

        if (! class_exists(ThemeChain::class) || ! $container->bound(ThemeChain::class)) {
            return null;
        }

        foreach ($container->make(ThemeChain::class)->layers as $layer) {
            if (is_dir($layer->path.'/demo/'.$module)) {
                return $layer->path.'/demo/'.$module;
            }
        }

        return null;
    }
}
