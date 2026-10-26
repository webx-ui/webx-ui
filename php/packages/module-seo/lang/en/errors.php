<?php

declare(strict_types=1);

return [
    'bad-regex' => 'This is not a regular expression PHP can compile.',
    'bad-json-ld' => 'This has to be a JSON-LD object, or a list of them.',
    'foreign-host' => 'This address is on another site. Only this site\'s addresses work here.',
    'service-address' => 'This is a service address (the panel, a preview, sign-in or OAuth), not a page of the site. It has no SEO of its own.',
    'self-loop' => 'This redirect sends the address to itself, which would loop forever.',
];
