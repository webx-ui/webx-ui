<?php

declare(strict_types=1);

return [
    'title' => 'Search index',
    'lead' => 'The catalogue searches, filters and counts with Manticore. The panel answers from the database when it does not.',
    'refresh' => 'Refresh',
    'load-failed' => 'Could not load the state of the index.',

    'connection' => 'Server',
    'address' => 'Address',
    'prefix' => 'Table prefix',
    'version' => 'Version',
    'available' => 'Answers',
    'unavailable' => 'Does not answer',

    'tables' => 'Tables',
    'language' => 'Language',
    'table' => 'Table',
    'in-index' => 'In the index',
    'in-database' => 'In the database',
    'state' => 'State',
    'state-ready' => 'Up to date',
    'state-stale' => 'Schema out of date',
    'state-missing' => 'Missing',
    'filling' => 'A rebuild is filling the table beside it.',
    'no-tables' => 'The server did not say which tables it has.',

    'queue' => 'Queue',
    'waiting' => 'Waiting to be written',
    'oldest' => 'The oldest since',
    'queue-empty' => 'Every saved product is in the index.',

    'outdated' => 'The index is not of the schema the catalogue writes now',
    'outdated-text' => 'The storefront answers from the old tables meanwhile, but what they lack — a new filter, codes — is not searched. A rebuild fills new tables beside the live ones and swaps them in; on a large catalogue it is minutes of load, so choose the time.',
    'rebuild' => 'Rebuild',
    'rebuild-again' => 'Rebuild again',
    'rebuild-title' => 'Rebuild the search index?',
    'rebuild-text' => 'Every product is written again into new tables, which then replace the live ones. The storefront keeps working on the old tables meanwhile.',
    'rebuild-busy' => 'A rebuild is already under way.',
    'rebuild-queued' => 'Waiting for the queue worker',
    'rebuild-running' => 'Rebuilding: :done of :total',
    'rebuild-done' => 'Rebuilt: :done products',
    'rebuild-failed' => 'The rebuild failed',
    'rebuild-stalled' => 'Nothing has been heard from the rebuild for a quarter of an hour: the queue worker is not running or stopped. Start it again once the worker runs.',
    'rebuild-started' => 'The rebuild is queued.',
    'cancel' => 'Cancel',
];
