<?php

declare(strict_types=1);

return [
    'rules' => 'Rules',
    'redirects' => 'Redirects',
    'test' => 'Check an address',

    'new-rule' => 'New rule',
    'new-redirect' => 'New redirect',
    'search-rules' => 'Search by address',
    'search-redirects' => 'Search by address or destination',
    'empty' => 'Nothing here yet.',

    'address' => 'Address',
    'kind' => 'Kind',
    'priority' => 'Priority',
    'state' => 'State',
    'active' => 'Active',
    'inactive' => 'Off',
    'title' => 'Title',
    'target' => 'Destination',
    'status' => 'Code',
    'hits' => 'Hits',
    'last-hit' => 'Last used',
    'never' => 'never',
    'loop' => 'Points at itself',

    'any-kind' => 'Any kind',
    'exact' => 'Exact',
    'mask' => 'Mask',
    'regex' => 'Regular expression',

    'rule' => 'Rule',
    'redirect' => 'Redirect',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'delete' => 'Delete',
    'saved' => 'Saved.',
    'deleted' => 'Deleted.',
    'failed' => 'Some values were not accepted. Check the highlighted fields.',
    'delete-title' => 'Delete :pattern?',
    'delete-text' => 'This cannot be undone.',

    'address-help' => 'A path with its query string. In a mask, * is one segment and ** is any number of them; a regular expression is stored as written, delimiters and all.',
    'target-help' => 'A path on this site, or a full address elsewhere. $1 puts back what a mask caught.',
    'priority-help' => 'Among rules of the same kind, the higher number wins.',

    'test-placeholder' => '/catalog/shoes?page=2',
    'test-run' => 'Check',
    'test-empty' => 'Type an address to see what the site will say about it.',
    'test-matched' => 'Matched rule',
    'test-none' => 'No rule matches this address.',
    'test-redirected' => 'This address is redirected to :target with a :status.',
    'test-result' => 'What the page will say',
    'test-chain' => 'Where each part came from',
];
