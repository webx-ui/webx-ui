<?php

declare(strict_types=1);

return [
    'tab' => 'Audit',
    'base-url' => 'Address to audit',
    'base-url-help' => 'Empty means APP_URL. The audit asks the site for its pages under this address.',
    'resolve-to' => 'Connect to',
    'resolve-to-help' => 'An IP address or host to open the connection to, with the public name kept in the request. Empty means what DNS says. For Docker and servers behind NAT.',
    'other-hosts' => 'Other addresses of this site',
    'other-hosts-help' => 'Development stands, staging and old domains, one per line. A link to any of them is an error wherever it is found.',
];
