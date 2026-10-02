<?php

declare(strict_types=1);

return [
    'seo' => [
        'redirect_chain' => [
            'title' => 'Redirects that lead to redirects',
            'found' => 'A redirect in the SEO table points at an address that is redirected again.',
            'why' => 'Every hop is another round trip for the visitor, and search engines stop following after a few.',
            'fix' => 'Point the first redirect straight at the last address — the fix button does it for every exact redirect of the chain.',
        ],
        'title_duplicate' => [
            'title' => 'The same title in several SEO cards',
            'found' => 'Several entities have the same title written in their SEO card, in the same language.',
            'why' => 'Two pages that call themselves the same compete with each other in search, and neither looks like the answer.',
            'fix' => 'Give each page a title that says what is on it and nowhere else.',
        ],
        'redirect_broken' => [
            'title' => 'Redirects to a broken page',
            'found' => 'An exact redirect sends visitors to an address that answered with an error during the crawl.',
            'why' => 'The visitor who followed an old link lands on an error page, and the weight of the old address is lost.',
            'fix' => 'Point the redirect at a page that exists, or restore the page.',
        ],
        'rule_dead' => [
            'title' => 'SEO rules for addresses that are gone',
            'found' => 'An exact SEO rule is written for an address that answered 404 or 410 during the crawl.',
            'why' => 'Nothing harmful, but the rule is for nobody, and it hides the fact that the page it was written for has gone.',
            'fix' => 'Delete the rule, or add a redirect from that address if the page has moved.',
        ],
    ],
];
