<?php

declare(strict_types=1);

return [
    'blocks' => [
        'prune-stray' => [
            'title' => 'Remove the stray values',
            'description' => 'Takes out of this entity the values of fields its block types do not define, in what the site shows and in the draft. Nothing changes on the site.',
        ],
    ],
];
