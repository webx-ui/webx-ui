<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The menus the templates of this site ask for
    |--------------------------------------------------------------------------
    |
    | A declared menu is visible in the panel from the first day, empty, and its row in `menus`
    | appears the first time somebody saves it — no write on boot and no synchronise command.
    | Its key is what a template names it by, so the key cannot be renamed and the menu cannot
    | be deleted; an administrator who needs a menu of their own makes one in the panel and
    | removes it there too.
    |
    | `variants` is the list of looks this menu offers, which the site's own markup then divides
    | by. A menu that names none gets the fallback below.
    |
    */

    'menus' => [
        'header' => ['title' => 'Header', 'variants' => ['link', 'button']],
        'footer' => ['title' => 'Footer'],
    ],

    'variants' => ['link'],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | A backstop, not the mechanism: every edit forgets its own keys, publishing a page forgets
    | the menus that page stands in, and so on for every source a menu reads from. The hour is
    | for what raises no model event at all — a bulk rebuild, an import, a hand-written UPDATE.
    |
    | `enabled` is here because "my change has not arrived" never looks like a cache; it looks
    | like a broken save. It is the first thing to switch off while finding out.
    |
    | Note that `mergeConfigFrom` merges one level deep, so a published copy of this file that
    | names one key inside `cache` replaces the whole section. Both values are read with a
    | default of their own for that reason.
    |
    */

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
    ],

];
