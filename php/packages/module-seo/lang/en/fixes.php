<?php

declare(strict_types=1);

return [
    'seo' => [
        'normalise-host' => [
            'title' => 'Redirect to the main mirror',
            'description' => 'Turns on “Main mirror” in the SEO settings: the other name answers with one 301 to this one.',
        ],
        'normalise-https' => [
            'title' => 'Redirect to https',
            'description' => 'Turns on “Always https” in the SEO settings: an address opened over http answers with one 301 to https.',
        ],
        'normalise-slashes' => [
            'title' => 'Collapse double slashes',
            'description' => 'Turns on “Collapse double slashes” in the SEO settings.',
        ],
        'normalise-index' => [
            'title' => 'Cut index files',
            'description' => 'Turns on “Cut index files” in the SEO settings: /index.php and /index.html redirect to the folder.',
        ],
        'normalise-trailing' => [
            'title' => 'One form of the slash at the end',
            'description' => 'Sets “Slash at the end” in the SEO settings to the form the site’s own links use.',
        ],
        'normalise-case' => [
            'title' => 'Redirect to lower case',
            'description' => 'Turns on “Lower case” in the SEO settings: /About answers with one 301 to /about.',
        ],
        'collapse-chain' => [
            'title' => 'Collapse the chain',
            'description' => 'Points every exact redirect of the chain straight at the address it ends at.',
        ],
        'robots-sitemap' => [
            'title' => 'Add the Sitemap line',
            'description' => 'Writes the Sitemap: line with the address of the sitemap into robots.txt in the SEO settings.',
        ],
    ],
];
