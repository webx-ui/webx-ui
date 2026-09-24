<?php

declare(strict_types=1);

/*
 * What a block shows from another section (`wx-collection`): the refusals of the server, then the
 * words of the field that makes the choice.
 */

return [
    'unknown-source' => 'There is no “:source” on this site to show records from.',
    'unknown-category' => 'One of the chosen categories no longer exists.',
    'limit' => 'How many to show: a whole number from 1 to :max, or empty for all.',
    'flag' => 'This switch takes yes or no.',

    'field-source' => 'Shows records from “:source”.',
    'field-unavailable' => 'Records from “:source” cannot be chosen here: the section is not installed, or you have no access to it.',
    'field-categories' => 'Categories',
    'field-all' => 'All categories',
    'field-no-categories' => 'No such category.',
    'field-limit' => 'How many to show',
    'field-limit-all' => 'All',
    'field-filter' => 'A filter by category above the list',
    'field-markup' => 'Markup for search engines',
    'field-markup-auto-on' => 'By default it is on: the block shows every category.',
    'field-markup-auto-off' => 'By default it is off: search engines ask not to mark the same records up on several pages, and a chosen category usually stands on several.',
    'field-markup-reset' => 'Back to the default',
];
