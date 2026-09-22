<?php

declare(strict_types=1);

use WebxUi\Menu\Menus;
use WebxUi\Menu\Rendering\MenuTree;

if (! function_exists('menu')) {
    /**
     * `menu('header')` — the items of a menu, ready to print, in the language being rendered.
     *
     *     menu('header');              // a MenuTree
     *     menu('footer')->flat();      // the same items without the nesting
     *     menu('header', locale: 'uk');
     *
     * Guarded, because the name is short enough that a site may well have taken it first — and
     * a package that redeclares a function of the application is a fatal error at boot rather
     * than a conflict somebody can work around.
     */
    function menu(string $key, ?string $locale = null): MenuTree
    {
        return app(Menus::class)->tree($key, $locale);
    }
}
