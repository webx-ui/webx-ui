<?php

declare(strict_types=1);

return [
    'empty' => 'Donor, acceptor and anchor are all required.',
    'anchor-long' => 'The anchor is longer than 255 characters.',
    'foreign-host' => 'The address is on another site; interlinking is internal.',
    'self' => 'A page cannot link to itself.',
    'duplicate' => 'This page is already linked in the block.',
    'not-found' => 'Nothing on the site answers this address.',
    'redirected' => ':from redirects; its target :to is used instead.',
    'donor-taken' => 'This page already has an interlinking block.',
    'unreadable' => 'The file could not be read as CSV or XLSX.',
];
