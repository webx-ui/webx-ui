<?php

declare(strict_types=1);

return [
    'title' => 'Name',
    'code' => 'Code',
    'code-help' => 'Latin letters, digits and hyphens: the status in the filter address (stock_in-stock) and in templates. Made from the name when left empty.',
    'color' => 'Tone',
    'color-help' => 'The site paints the status line in this tone; the panel shows a tag of the same tone.',
    'tones' => [
        'neutral' => 'Neutral',
        'primary' => 'Primary',
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Danger',
        'info' => 'Info',
    ],
    'purchasable' => 'Can be bought',
    'purchasable-help' => 'Off, a product in this status cannot be bought, and its name is what the site says instead of the buy button.',
    'default' => 'Default',
    'default-help' => 'The status of every product nobody set a status for, and of a new product. To change it, make another status the default.',
    'filterable' => 'In the filter',
    'filterable-help' => 'Off, the status is not a choice of the catalogue filter.',
    'new' => 'New status',
    'empty' => 'No stock statuses yet.',
    'empty-help' => 'A status says whether a product is in stock and whether it can be bought.',
    'order' => 'The order here is the order of the filter',
    'hidden' => 'Not in the filter',
    'count' => 'Products: :count',
    'show-items' => 'Show its products',
    'delete-blocked' => 'While products are in it, it cannot go — move them to another status first.',
    'delete-text' => 'It goes to the bin and leaves the filter.',
    'deleted' => 'The status is in the bin.',
    'saved' => 'The status is saved.',
];
