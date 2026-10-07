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
    | What the site provides
    |---------------------------------------------------------------------------
    |
    | The names the site's own bundle hands to blocks through `webx.provide()`
    | — `swiper`, `gsap`, whatever it has built. Data, not code: the editor
    | shows the list beside the script field, and MCP hands it to an agent, so
    | that a block asks for what this site actually has.
    |
    */

    'provides' => [],

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

    /*
    |---------------------------------------------------------------------------
    | Thumbnails
    |---------------------------------------------------------------------------
    |
    | The picture of every type on its sample, in the list of types and the
    | picker, is drawn once and kept. Any change to any type — a version, a
    | publication, a setting, a delete, an import — and `webx:blocks:clear`
    | throw all of them away. A type that reads records draws those records,
    | which change on their own: `ttl` (seconds) is how stale its picture may
    | get. `cache` false draws them on every request, as before.
    |
    */

    'thumbnails' => [
        'cache' => env('WEBX_BLOCKS_THUMBNAILS_CACHE', true),
        'ttl' => 3600,
    ],

    /*
    |---------------------------------------------------------------------------
    | Bundles
    |---------------------------------------------------------------------------
    |
    | The styles and scripts of a page are served as one file each, named by
    | the hash of the types and versions on it, under this path prefix. A set
    | whose CSS and JS together weigh no more than `inline_below` bytes is
    | printed inline instead; 0 never inlines.
    |
    */

    'bundles' => [
        'path' => 'blocks',
        'inline_below' => 0,
    ],

    /*
    |---------------------------------------------------------------------------
    | Entities
    |---------------------------------------------------------------------------
    |
    | The models that use `HasBlocks`, for `webx:blocks:bundles --warm`: the
    | bundle of every row of every model listed here is written ahead of the
    | first visitor. The package does not know your entities; name them.
    |
    */

    'entities' => [],

    /*
    |---------------------------------------------------------------------------
    | Views
    |---------------------------------------------------------------------------
    |
    | The folders whose Blade views are read for `<x-webx-block type="…">`, so
    | that a type a view calls by tag is counted as used: `blocks_usage` lists
    | those views and `blocks_delete` refuses the type unless forced. Null is
    | the application's `resources/views`.
    |
    */

    'views' => null,

    /*
    |---------------------------------------------------------------------------
    | Preview
    |---------------------------------------------------------------------------
    |
    | `/{path}/{type}/{id}?token=…` shows the draft of an entity as the page it
    | will be: the same handler, the same view, with the draft laid over the
    | columns and the drafts of the block types in place of the published ones.
    | The token is signed with the application key and lives `ttl` minutes;
    | the panel asks for a fresh one when it opens the preview. The route runs
    | through `middleware`, so that the site's own locale and session handling
    | apply to the preview exactly as they do to the page.
    |
    */

    'preview' => [
        'path' => '_preview',
        'ttl' => 60,
        // `webx.locale` and not `web` alone: the language of a page is decided by a middleware
        // the site puts on its own routes, so a preview without it answered in the
        // application's default — an editor was shown their Russian page in English.
        'middleware' => ['web', 'webx.locale'],
    ],

    /*
    |---------------------------------------------------------------------------
    | The layout the editor's stage stands in
    |---------------------------------------------------------------------------
    |
    | The name of a Blade component: `'layout'` for the `<x-layout>` a site keeps
    | in `resources/views/components/layout.blade.php` — the same one its pages
    | stand in. The block editor draws the block inside it, header, footer and
    | the site's styles included, so that what an editor sees is the block on
    | the site rather than the block against the browser's default styles.
    | Empty prints the package's own `webx-blocks::standalone`, a bare document.
    |
    | The deal is the one every module has: a `head` slot and the default slot.
    |
    */

    'layout' => env('WEBX_BLOCKS_LAYOUT'),

    /*
    |---------------------------------------------------------------------------
    | Regions of the layout
    |---------------------------------------------------------------------------
    |
    | Named places of the site's layout whose content is a tree of blocks,
    | edited in the panel like a page, with the markup from code printed for
    | as long as the region is empty or unpublished:
    |
    |     <x-webx-blocks::region name="header" fallback="components.header" />
    |
    | A region exists where the layout prints that tag and is declared here;
    | the row behind it appears the first time it is saved. Empty by default,
    | because whoever writes the layout is the one who knows its regions.
    |
    |     'regions' => [
    |         'header' => [
    |             'title' => 'trans::webx-blocks::regions.header',
    |             'description' => 'Top of every page: logo, menu, the call to action.',
    |             'allow' => null,   // the block types allowed at the top; null for any
    |             'max' => null,     // how many blocks at the top; null for no limit
    |         ],
    |         'footer' => ['title' => 'trans::webx-blocks::regions.footer'],
    |     ],
    |
    | A name is lower-case letters, digits and dashes. A block type offered
    | only here says so with `"region:header"` in its `allowed_in`.
    |
    */

    'regions' => [],

];
