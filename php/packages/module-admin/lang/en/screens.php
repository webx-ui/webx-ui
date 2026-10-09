<?php

declare(strict_types=1);

return [
    'unknown' => 'There is no screen named :name.',
    'row' => 'Row :number: :message',
    'row-shape' => 'This row is not a set of fields.',
    // The card a module's screen keeps for the fields a project patches in (`project-fields`).
    'project-fields' => 'More',
    // The words of a list of records on a described screen; the core has English only.
    'repeater' => [
        'add' => 'Add',
        'remove' => 'Remove',
        'remove-question' => 'Remove this row?',
        'cancel' => 'Cancel',
        'reorder' => 'Reorder',
        'empty' => 'Nothing here yet',
    ],
    'not-a-language-map' => ':field is translated: send a value per language, { "en": … }, not one list.',
];
