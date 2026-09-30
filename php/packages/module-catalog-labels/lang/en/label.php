<?php

declare(strict_types=1);

return [
    'title' => 'Name',
    'code' => 'Code',
    'code-help' => 'Latin letters, digits and hyphens: the label in the filter address (label_sale) and in templates. Made from the name when left empty.',
    'color' => 'Tone',
    'color-help' => 'The site paints the badge in this tone; the panel shows a tag of the same tone.',
    'tones' => [
        'neutral' => 'Neutral',
        'primary' => 'Primary',
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Danger',
        'info' => 'Info',
    ],
    'badge' => 'Badge on the card',
    'badge-help' => 'Off for a service label that only picks products, such as one for the newsletter.',
    'filterable' => 'In the filter',
    'filterable-help' => 'Off, the label is not a choice of the catalogue filter.',
];
