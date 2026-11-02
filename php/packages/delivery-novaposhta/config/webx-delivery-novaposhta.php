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
    | somebody else's account (a development copy with a client's key): directories, senders and
    | tracking still work, nothing is written there.
    */
    'read_only' => (bool) env('NOVAPOSHTA_READ_ONLY', false),

    /*
    | The journal of calls: one line per call — time, model, method, page, outcome — never the key
    | or the data. It is how one checks which methods a site called. null — no journal.
    */
    'journal' => env('NOVAPOSHTA_JOURNAL', storage_path('logs/novaposhta-calls.log')),
];
