<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | How the site knocks on its own door
    |---------------------------------------------------------------------------
    |
    | Every request goes to the public name with the real Host and SNI. Where
    | the TCP connection goes is the `audit.resolve-to` setting — empty means
    | whatever DNS says — so an audit works in Docker and behind NAT without
    | hairpinning, and still sees exactly what a visitor sees (decision 6).
    |
    */

    'user_agent' => 'WebxAudit/1.0',

    // Seconds a single request may take before it counts as not answering.
    'timeout' => 15,

    /*
    |---------------------------------------------------------------------------
    | A run, a piece at a time
    |---------------------------------------------------------------------------
    |
    | One job works for this many seconds and queues the next one (decision 5):
    | no host likes a twenty-minute job, and a deploy or a worker's time limit
    | cuts a run between pieces rather than inside one.
    |
    */

    'job_seconds' => 30,

    /*
    |---------------------------------------------------------------------------
    | The crawl
    |---------------------------------------------------------------------------
    |
    | Polite by default (decision 7): two requests at a time and a thousand
    | pages at most. Snapshots of pages are kept for the last few full runs —
    | the findings outlive them.
    |
    */

    'pages_limit' => 1000,

    'concurrency' => 2,

    'keep_snapshots' => 5,

    // What the pages load and where their external links lead, asked once
    // per run however many pages share it (§3, stage 5): pictures first,
    // external pages last, this many addresses at most.
    'resources_limit' => 2000,

    /*
    |---------------------------------------------------------------------------
    | Thresholds
    |---------------------------------------------------------------------------
    |
    | The defaults of §5. They move to the section's settings in A5; until then
    | a project that disagrees publishes this file.
    |
    */

    'thresholds' => [
        // A certificate that runs out sooner than this is an error, then a warning.
        'tls_error_days' => 14,
        'tls_warning_days' => 30,
        // CSS, JS and pictures cached for less than this are worth a warning.
        'static_cache_seconds' => 7 * 24 * 3600,
        // The scheduler is expected to have run within this many minutes.
        'schedule_minutes' => 60,
        // The page (§5.5): lengths in characters, the share of text in percent.
        'title_min' => 30,
        'title_max' => 60,
        'description_min' => 70,
        'description_max' => 160,
        'thin_words' => 250,
        'text_ratio' => 10,
        'url_length' => 115,
        'ttfb_ms' => 600,
        'html_bytes' => 1024 * 1024,
        'depth' => 3,
        'external_links' => 100,
        // Pictures: heavier than this (KB) is a warning; a JPEG or PNG heavier
        // than the second to a browser that takes WebP and AVIF is a notice.
        'image_kb' => 300,
        'image_format_kb' => 100,
        // The size an Open Graph picture should be at least.
        'og_width' => 1200,
        'og_height' => 630,
    ],

    /*
    |---------------------------------------------------------------------------
    | What has to be a development stand
    |---------------------------------------------------------------------------
    |
    | Besides the hosts an administrator lists as "other addresses of this
    | site", a host is a stand when it is a loopback or private address, when
    | it ends in one of these zones, or when its first label is one of these
    | words (§5.6). The host of APP_URL is never a stand — otherwise a local
    | run on `shop.local` would call the whole site an error.
    |
    */

    'dev_zones' => ['local', 'localhost', 'test', 'example', 'invalid', 'internal'],

    'dev_words' => ['dev', 'stage', 'staging', 'test', 'preview', 'demo'],

];
