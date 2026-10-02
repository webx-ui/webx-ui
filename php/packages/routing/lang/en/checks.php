<?php

declare(strict_types=1);

return [
    'routing' => [
        'orphan' => [
            'title' => 'Addresses of deleted records',
            'found' => 'A row of the address registry points at a record that no longer exists.',
            'why' => 'The address answers 404 while it still holds its name, so a new record cannot take it.',
            'fix' => 'Run php artisan webx:routes:rebuild, or restore the record if it was deleted by mistake.',
        ],
        'alias_broken' => [
            'title' => 'Old addresses that lead nowhere',
            'found' => 'An alias — the old address kept after a slug changed — leads to no address, or to another alias.',
            'why' => 'A visitor with an old link gets a 404, or a redirect to a redirect.',
            'fix' => 'Run php artisan webx:routes:rebuild, or delete the alias on the «Automatic» tab of the SEO section.',
        ],
        'shadowed' => [
            'title' => 'Addresses the application answers itself',
            'found' => 'A route of the application has the same address as a record of the registry.',
            'why' => 'The record is never shown: the application’s route answers first.',
            'fix' => 'Change the slug of the record, or the route of the application.',
        ],
        'no_address' => [
            'title' => 'Records without an address',
            'found' => 'A record that should have an address in a language has none.',
            'why' => 'The page cannot be opened, is not in the sitemap and cannot be linked to.',
            'fix' => 'Save the record again, or run php artisan webx:routes:rebuild.',
        ],
        'unknown_type' => [
            'title' => 'Addresses of a module that is not installed',
            'found' => 'The registry holds addresses of a type no installed module knows.',
            'why' => 'They answer nothing and still hold their names against every new record.',
            'fix' => 'Remove those rows, or install the module again.',
        ],
    ],
];
