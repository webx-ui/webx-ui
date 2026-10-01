<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The root of the catalogue
    |---------------------------------------------------------------------------
    |
    | `/catalog/` — a page with the top categories and a filter over the whole
    | catalogue. Off by default: in most shops a top category is just a list,
    | and a page of its own is one nobody asked for. Off means no address at all.
    |
    */

    'root' => [
        'enabled' => (bool) env('WEBX_CATALOG_ROOT', false),
        'prefix' => env('WEBX_CATALOG_ROOT_PREFIX', 'catalog'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Price and the optional fields
    |---------------------------------------------------------------------------
    |
    | The columns are always there; switched off, a field is gone from the form,
    | the facets and the exchange columns alike. The currency is one for the
    | whole site, an ISO 4217 code; only the markup reads it, and a product's
    | `Offer` is left out of it while it is not set.
    |
    | `facets` is the «Filters» tab of a category: which filters it shows and in
    | what order. Off by default — with the category and the price alone there
    | is nothing to arrange; a satellite that brings filters of its own (the
    | properties) switches it on. Off, every category shows every filter, and
    | what was chosen before is kept but not applied.
    |
    */

    'price' => [
        'enabled' => (bool) env('WEBX_CATALOG_PRICE', true),
        'currency' => env('WEBX_CATALOG_CURRENCY'),
    ],

    'fields' => [
        'barcode' => (bool) env('WEBX_CATALOG_BARCODE', true),
        'facets' => (bool) env('WEBX_CATALOG_CATEGORY_FACETS', false),
        'video' => (bool) env('WEBX_CATALOG_VIDEO', true),
    ],

    /*
    |---------------------------------------------------------------------------
    | Units of measure
    |---------------------------------------------------------------------------
    |
    | Keys, not words: the label of each is `webx-catalog::units.<key>` in the
    | module's dictionary, so a site adds a unit here and its word there.
    |
    */

    'units' => ['pcs', 'kg', 'g', 'm', 'm2', 'm3', 'l', 'pack', 'set'],

    /*
    |---------------------------------------------------------------------------
    | The names of the filters in an address
    |---------------------------------------------------------------------------
    |
    | `/laptops/price_100-500`: `price` is the facet's code. Per language, so a
    | Russian address reads `/ru/noutbuki/cena_100-500`. Facet key → language →
    | code, `[a-z0-9-]`, unique among the facets of that language. A language
    | not named here, and a facet not named at all, keeps the English code.
    |
    | 'facet_codes' => ['price' => ['ru' => 'cena'], 'brand' => ['ru' => 'brend']],
    |
    */

    'facet_codes' => [],

    'default_unit' => 'pcs',

    /*
    |---------------------------------------------------------------------------
    | The engine behind the catalogue, search and facets
    |---------------------------------------------------------------------------
    |
    | `sql` is the database itself, honest up to a couple of thousand live
    | products; `manticore` comes with `webx-ui/module-catalog-manticore`. Past the
    | limit `webx:doctor` says so.
    |
    */

    'engine' => env('WEBX_CATALOG_ENGINE', 'sql'),

    'sql_engine_limit' => 2000,

    'per_page' => 24,

    /*
    |---------------------------------------------------------------------------
    | Sorting
    |---------------------------------------------------------------------------
    |
    | The sorts a reader is offered, in this order, and the steps of the
    | default one: the hand-set priority, then popularity, then the newest.
    |
    */

    'sorts' => ['default', 'price_asc', 'price_desc', 'popular', 'new', 'name'],

    'default_sort' => ['priority' => 'desc', 'score' => 'desc', 'created_at' => 'desc'],

    'popularity' => [
        'views' => true,
        'decay' => 0.9,
        'weights' => ['views' => 1],
        'touch_threshold' => 0.05,
    ],

    /*
    |---------------------------------------------------------------------------
    | The gallery of a product
    |---------------------------------------------------------------------------
    |
    | Straight onto this disk under `catalog/{id div 1000}/{id}/`, not into the
    | media library: half a million product photos belong to no editor's tree.
    |
    */

    'images' => [
        'disk' => env('WEBX_CATALOG_IMAGES_DISK', 'public'),
        'max_size_kb' => 10240,
    ],

    /*
    |---------------------------------------------------------------------------
    | Videos in the gallery
    |---------------------------------------------------------------------------
    |
    | A video is attached to a picture of the gallery, which is its poster: a
    | link to YouTube, or a file of our own, stored as it is — no re-encoding.
    | A file comes in pieces through the panel's chunked upload, so the limit
    | here is the only one: PHP's and nginx's never see a whole file. The type
    | is checked by the content. `fields.video` switched off hides the player
    | and refuses new videos; the ones attached are kept.
    |
    */

    'videos' => [
        'max_size_mb' => 2048,
        'types' => ['video/mp4', 'video/webm'],
    ],

    'bulk' => ['chunk' => 500, 'sync_limit' => 50],

    /*
    |---------------------------------------------------------------------------
    | Import and export of CSV and XLSX
    |---------------------------------------------------------------------------
    |
    | A file of `sync_limit` rows or fewer is done inside the request; a larger
    | one goes to the queue in chunks of `chunk` rows, one transaction each, a
    | job doing chunks for `job_seconds` before it hands over to the next. After
    | `max_errors` bad rows a run stops. A CSV that is neither UTF-8 nor marked
    | by a BOM is read as `csv_fallback_encoding`. Files — what was imported,
    | what was exported — live on `disk` for `keep_hours`; the runs and their
    | errors for `keep_runs_days`.
    |
    */

    'exchange' => [
        'chunk' => 200,
        'sync_limit' => 50,
        'job_seconds' => 60,
        'max_errors' => 1000,
        'max_bytes' => 200 * 1024 * 1024,
        'csv_fallback_encoding' => 'Windows-1252',
        'disk' => env('WEBX_CATALOG_EXCHANGE_DISK', 'local'),
        'keep_hours' => 24,
        'keep_runs_days' => 90,
    ],

    /*
    |---------------------------------------------------------------------------
    | The layout the storefront stands in
    |---------------------------------------------------------------------------
    |
    | The name of a Blade component — `'layout'` for the `<x-layout>` a site keeps
    | in `resources/views/components/layout.blade.php`. Empty prints the
    | package's own bare document. Two slots: `head` and the default one.
    |
    | The markup of each page is overridden the usual way, by a view of the same
    | name in `resources/views/vendor/webx-catalog/`.
    |
    */

    'layout' => env('WEBX_CATALOG_LAYOUT'),

    /*
    |---------------------------------------------------------------------------
    | The middleware of the root and the search
    |---------------------------------------------------------------------------
    |
    | The two storefront pages that are routes rather than rows of the address
    | registry. The same stack the registry answers through: a session, and the
    | decision about which language the request is in.
    |
    */

    'middleware' => ['web', 'webx.locale'],

];
