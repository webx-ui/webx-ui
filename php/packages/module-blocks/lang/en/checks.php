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
];
