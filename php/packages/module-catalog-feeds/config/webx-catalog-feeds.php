<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The prefix of a feed's address
    |--------------------------------------------------------------------------
    |
    | A feed answers at `/{path}/{key}.{ext}` (§7.5 of the feeds spec). Changing it moves every
    | address a marketplace already holds.
    |
    */

    'path' => env('WEBX_CATALOG_FEEDS_PATH', 'feeds'),

    /*
    |--------------------------------------------------------------------------
    | A build (§7.1 and §7.7 of the feeds spec)
    |--------------------------------------------------------------------------
    |
    | `chunk` products a page. A job works for `job_seconds` and queues the next one, so a deploy cuts
    | a build between pages; more than 60 is not taken, the job's timeout stays under the queue's
    | `retry_after`. `disk` keeps the files and must be private: what
    | guards them is the key. `max_bytes` stays under the 4 GB Google takes. `max_rejects` rows of
    | the journal name a product; the counters go on. A build silent for `stale_minutes` is closed.
    |
    */

    'chunk' => 500,

    'job_seconds' => 60,

    'disk' => env('WEBX_CATALOG_FEEDS_DISK', 'local'),

    'max_bytes' => 3 * 1024 ** 3,

    'max_rejects' => 1000,

    'stale_minutes' => 30,

    /*
    |--------------------------------------------------------------------------
    | Mappings (§3.3 and §6.2 of the feeds spec)
    |--------------------------------------------------------------------------
    |
    | `max_label_values` different values of a custom label before the journal warns: Google takes
    | 1000 a label on an account, and a build sees only its own feed. `taxonomy_max_bytes` is how
    | large the file of a list may be, by address or uploaded; Google's are about a megabyte.
    |
    */

    'max_label_values' => 1000,

    'taxonomy_max_bytes' => 16 * 1024 * 1024,

    /*
    |--------------------------------------------------------------------------
    | The trial of a feed (§8 of the feeds spec)
    |--------------------------------------------------------------------------
    |
    | `sample` products go through a build's own code when a feed is saved or previewed. It runs
    | while a person waits, so it stays small.
    |
    */

    'sample' => 5,

    /*
    |--------------------------------------------------------------------------
    | Delivery (§7.3, §7.5 and §7.6 of the feeds spec)
    |--------------------------------------------------------------------------
    |
    | `keep_files` files stay behind the one served, for whoever is reading them, and the journal
    | goes back `keep_builds_days`. After a change of the catalogue a build waits for `quiet_minutes`
    | of silence, comes no oftener than `min_interval` and no later than `max_wait` after the first
    | change, all in minutes. `per_minute` requests a minute by one address to one key.
    |
    */

    'keep_files' => 2,

    'keep_builds_days' => 30,

    'quiet_minutes' => 10,

    'min_interval' => 60,

    'max_wait' => 360,

    'per_minute' => 60,

];
