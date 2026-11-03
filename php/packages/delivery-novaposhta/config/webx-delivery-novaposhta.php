<?php

declare(strict_types=1);

return [
    /*
    | The one address of the carrier's JSON API (§3.2): every model and method goes there by POST.
    */
    'url' => env('NOVAPOSHTA_API_URL', 'https://api.novaposhta.ua/v2.0/json/'),

    /*
    | Seconds to wait for an answer. The panel asks while a person waits, so it stays short.
    */
    'timeout' => (int) env('NOVAPOSHTA_TIMEOUT', 10),

    /*
    | Read only: the client refuses every method that changes the carrier's account — a waybill,
    | a recipient, an address — before a request is made. Turn it on wherever the key belongs to
    | somebody else's account (a development copy with a client's key): directories and senders
    | still work, nothing is written there; the tracking is off unless said otherwise.
    */
    'read_only' => (bool) env('NOVAPOSHTA_READ_ONLY', false),

    /*
    | The tracking of parcels (§9): one `getStatusDocuments` per batch of the base's schedule.
    | false — nothing is asked, every parcel stays as it is. null — on, but off on a read-only key:
    | somebody else's parcels are its buyers' data.
    */
    'tracking' => env('NOVAPOSHTA_TRACKING'),

    /*
    | The journal of calls: one line per call — time, model, method, page, outcome — never the key
    | or the data. It is how one checks which methods a site called. null — no journal.
    */
    'journal' => env('NOVAPOSHTA_JOURNAL', storage_path('logs/novaposhta-calls.log')),

    /*
    | The directories' sync (§6.6): at most this many requests a minute — a pause of 60 / N
    | seconds between them. The carrier refuses two directory requests within one second.
    */
    'sync' => [
        'requests_per_minute' => (int) env('NOVAPOSHTA_REQUESTS_PER_MINUTE', 60),

        /*
        | The streets (§6.4): one pull of a page asks `getStreet` at most `max_requests_per_pull`
        | times and starts no request after `pull_seconds` — a failed page is repeated whole.
        */
        'max_requests_per_pull' => (int) env('NOVAPOSHTA_MAX_REQUESTS_PER_PULL', 20),
        'pull_seconds' => (float) env('NOVAPOSHTA_PULL_SECONDS', 20),

        /*
        | When the streets run: a cron, or `manual` — by hand only. Weekly by default; a read-only
        | key (somebody else's account) runs them by hand only, unless said otherwise. null — so.
        */
        'streets_cron' => env('NOVAPOSHTA_STREETS_CRON'),

        /*
        | Walk the streets of only this many first towns with a courier, not of all ~10 000 —
        | ~10 500 requests; 0 — every town. null — every town, but a read-only key walks 20: a
        | pass on somebody else's account must not spend its limits.
        */
        'streets_sample' => env('NOVAPOSHTA_STREETS_SAMPLE'),
    ],
];
