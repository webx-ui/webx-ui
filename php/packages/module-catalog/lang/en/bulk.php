<?php

declare(strict_types=1);

return [
    'actions' => [
        'publish' => 'Publish',
        'unpublish' => 'Unpublish',
        'set-category' => 'Set the main category',
        'add-category' => 'Add a category',
        'remove-category' => 'Remove a category',
        'delete' => 'Delete',
        'restore' => 'Restore',
    ],
    'params' => [
        'category' => 'Category',
    ],
    'errors' => [
        'selection' => 'Choose the products: a list of ids or the query of the list.',
        'too-many' => 'Too many ids at once; send the query of the list instead.',
        'unknown-action' => 'There is no such bulk action. Known: :known',
        'forbidden' => 'This action needs the :permission permission.',
        'gone' => 'The product is no longer where the run found it.',
    ],
];
