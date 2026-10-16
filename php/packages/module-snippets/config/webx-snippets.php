<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Places
    |---------------------------------------------------------------------------
    |
    | Where a snippet may stand: a key and the words the panel shows for it —
    | plain words or a translation key. `head`, `body_start` and `body_end` are
    | always there, printed by the layout of theme-default; naming one of them
    | here only changes its words.
    |
    | A place of your own is printed by the theme or the site with the same
    | component the built-in ones are:
    |
    |     'places' => [
    |         'before-footer' => 'theme::snippets.before_footer',
    |     ],
    |
    |     <x-webx-snippets place="before-footer" />
    |
    | Take a place out of this list and its snippets stay in the database,
    | printed nowhere until they are moved — the panel and webx:doctor say so.
    |
    */

    'places' => [],

    /*
    |---------------------------------------------------------------------------
    | Environments that print every snippet
    |---------------------------------------------------------------------------
    |
    | Anywhere else (APP_ENV) only snippets marked "on dev and stage too" are
    | printed: a counter on a copy of the site would count visits that never
    | happened on the real one.
    |
    */

    'environments' => ['production'],

];
