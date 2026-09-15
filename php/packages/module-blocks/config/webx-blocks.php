<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Groups
    |---------------------------------------------------------------------------
    |
    | The sections of the list an editor picks a block from, in the order they
    | are shown. A block type names one of these in its `group` column; the
    | labels come from the panel's dictionary, so a site adds "promo" here and
    | translates it there. A list rather than free text in the column, because
    | free text gives "Content", "content" and "Contents" — three groups for one.
    |
    */

    'groups' => ['content', 'layout', 'media'],

    /*
    |---------------------------------------------------------------------------
    | Editing
    |---------------------------------------------------------------------------
    |
    | A block type is Blade, and Blade is PHP: whoever can save a block can run
    | anything the application can. Turn editing off on a production site whose
    | types arrive by import, and the section becomes read-only.
    |
    */

    'editing' => env('WEBX_BLOCKS_EDITING', true),

    /*
    |---------------------------------------------------------------------------
    | Nesting
    |---------------------------------------------------------------------------
    |
    | How many levels deep a container block may hold other blocks. Anything
    | below the limit is left out of the page and noted in the log: past a
    | handful of levels this is no longer a constructor but layout by mouse.
    |
    */

    'max_depth' => 5,

    /*
    |---------------------------------------------------------------------------
    | Compiled templates
    |---------------------------------------------------------------------------
    |
    | Where the compiled Blade of every block version is written. One file per
    | version, named by slug and number, so a new version is a new file and
    | nothing ever needs invalidating. Null means next to the application's own
    | compiled views, in a `blocks` directory of its own.
    |
    */

    'compiled' => env('WEBX_BLOCKS_COMPILED'),

    /*
    |---------------------------------------------------------------------------
    | Cache
    |---------------------------------------------------------------------------
    |
    | The published types — a few kilobytes of template each — are read once
    | and kept until a version is saved, published or deleted, and by the
    | `webx:blocks:clear` command.
    |
    */

    'cache' => [
        'enabled' => env('WEBX_BLOCKS_CACHE', true),
        'ttl' => 86400,
        'key' => 'webx.blocks.types',
    ],

];
