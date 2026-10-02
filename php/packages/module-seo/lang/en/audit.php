<?php

declare(strict_types=1);

return [
    'on' => 'On',
    'normalise-note' => 'Works for requests the web server hands to the site. If the finding stays after the next run, the web server answers that address itself — set the redirect there.',
    'robots-file-note' => 'The site has a public/robots.txt file. The web server serves it before the site is asked, so delete it for the setting to take effect.',
    'redirect-chain' => 'Hops before the page: :steps',
    'title-duplicate' => '“:title” — in :count cards',
    'redirect-broken' => ':target answers :status',
    'rule-dead' => 'The address answers :status',
];
