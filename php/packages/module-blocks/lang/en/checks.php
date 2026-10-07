<?php

declare(strict_types=1);

return [
    'no-marker' => 'No data-wx-block on the root: the script will not run, and the panel cannot highlight the block in the preview.',
    'stray-selectors' => 'Selectors outside the block prefix .b-:slug: :selectors',
    'bare-selectors' => 'Element selectors reach the whole site: :selectors',
    'media-query' => '@media measures the window. A block is sized by its container: use @container.',
    'variables-missing' => 'The template uses :variables, which the schema does not declare. Publishing will be refused.',
    'ok-marker' => 'The root carries data-wx-block.',
    'ok-prefix' => 'Every selector starts with .b-:slug.',
    'ok-bare' => 'No bare element selectors.',
    'ok-container' => 'Width is decided by container queries.',
    'ok-variables' => 'Every variable of the template is a field of the schema.',
    'blocks' => [
        'stray_values' => [
            'title' => 'Block values for fields the type does not have',
            'found' => 'Blocks hold values for fields their type does not define — left by an import or by a field taken out of the type.',
            'why' => 'Nothing of it reaches a visitor, but it is in the editor\'s data and in what an agent reads, and it turns up as a wrong line a block is recognised by.',
            'fix' => 'Remove them with the fix, or for the whole site with php artisan webx:blocks:prune. A block of a type that no longer exists is left alone. Repeater items are checked against the repeater\'s fields.',
        ],
    ],
    'syntax' => 'The template does not compile: :reason. Publishing will be refused.',
    'unknown-field-type' => 'Fields of a type this site does not know: :fields. The form draws a warning in their place and nothing checks their values.',
    'field-id' => 'Field ids must be letters, digits, _ and -, starting with a letter: :ids.',
];
