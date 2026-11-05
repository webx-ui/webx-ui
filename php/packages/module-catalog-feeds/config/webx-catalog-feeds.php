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

];
