<?php

declare(strict_types=1);

return [
    'config' => [
        'debug' => [
            'title' => 'Debug mode on a working domain',
            'found' => 'APP_DEBUG=true on a domain that is not a development stand.',
            'why' => 'Every error page shows the code, the queries and the environment, passwords included, to anyone who finds one.',
            'fix' => 'Set APP_DEBUG=false in .env and run php artisan config:cache.',
        ],
        'env' => [
            'title' => 'The environment is not production',
            'found' => 'APP_ENV is not production on a working domain.',
            'why' => 'Packages behave differently outside production: caches, error pages, mail and debugging tools.',
            'fix' => 'Set APP_ENV=production in .env and run php artisan config:cache.',
        ],
        'app_url' => [
            'title' => 'APP_URL does not match the site',
            'found' => 'APP_URL differs from the scheme and host the site answers on.',
            'why' => 'Every absolute address the site prints — the sitemap, canonical links, letters, file links — points somewhere else.',
            'fix' => 'Set APP_URL to the address visitors use, with https if the site has it, and run php artisan config:cache.',
        ],
        'queue' => [
            'title' => 'The queue runs inside the request',
            'found' => 'The queue driver is sync.',
            'why' => 'Letters and submissions are handled while the visitor waits, a slow mail server makes forms slow, and long jobs such as the audit cannot run from the panel.',
            'fix' => 'Use the database or redis queue and keep a worker running (php artisan queue:work under a supervisor).',
        ],
        'mail' => [
            'title' => 'Mail goes nowhere',
            'found' => 'The mailer writes letters to the log or to memory.',
            'why' => 'Every form says “sent” and nobody ever receives a letter.',
            'fix' => 'Configure a real mailer (SMTP or an API) in .env: MAIL_MAILER and its settings.',
        ],
        'schedule' => [
            'title' => 'The scheduler does not run',
            'found' => 'The scheduler has not run for longer than an hour.',
            'why' => 'Backups, the journal trim and everything else on the schedule silently stop.',
            'fix' => 'Add “* * * * * php artisan schedule:run” to the crontab of the site’s user.',
        ],
        'storage_link' => [
            'title' => 'No public/storage link',
            'found' => 'public/storage does not exist.',
            'why' => 'Every uploaded picture and file on the site answers 404.',
            'fix' => 'Run php artisan storage:link on the server.',
        ],
        'site_gate' => [
            'title' => 'The site is closed with a password',
            'found' => 'The site gate is on.',
            'why' => 'Search engines see nothing behind the password — right while the site is in testing, wrong after launch.',
            'fix' => 'Set WEBX_SITE_GATE=false when the site opens.',
        ],
    ],
    'host' => [
        'mirror' => [
            'title' => 'Two mirrors answer',
            'found' => 'Both www and the bare name answer 200.',
            'why' => 'Every page exists twice, and search engines split its weight between the copies.',
            'fix' => 'Redirect the second name to the main one with a single 301 in the web server.',
        ],
        'https' => [
            'title' => 'http does not lead to https in one step',
            'found' => 'http:// answers itself, leads elsewhere, or reaches https through a chain.',
            'why' => 'Visitors land on an insecure copy, and every extra step costs time and link weight.',
            'fix' => 'One 301 from http:// to https:// of the main host, in the web server.',
        ],
        'tls' => [
            'title' => 'Certificate problem',
            'found' => 'The certificate expires soon, names another host or is not trusted.',
            'why' => 'Browsers show a full-page warning and most visitors leave.',
            'fix' => 'Renew the certificate (check that auto-renewal works) and serve the full chain for this host.',
        ],
        'hsts' => [
            'title' => 'No HSTS',
            'found' => 'No Strict-Transport-Security header.',
            'why' => 'The first visit can still go over plain http and be intercepted.',
            'fix' => 'Add Strict-Transport-Security: max-age=31536000 in the web server once https is stable.',
        ],
        'index_files' => [
            'title' => 'Index files answer',
            'found' => '/index.php or another index file answers 200.',
            'why' => 'The page is available under a second address — a duplicate for search engines.',
            'fix' => 'Redirect index files to the address without them with a 301.',
        ],
        'slashes' => [
            'title' => 'Double slashes are not collapsed',
            'found' => 'An address with // answers 200.',
            'why' => 'Any mistyped link creates another copy of the page.',
            'fix' => 'Redirect addresses with repeated slashes to the collapsed one with a 301.',
        ],
        'trailing_slash' => [
            'title' => 'With and without the trailing slash',
            'found' => 'The same page answers with and without the trailing slash.',
            'why' => 'Two addresses for one page split its weight.',
            'fix' => 'Choose one form and redirect the other with a 301.',
        ],
        'case' => [
            'title' => 'Case is not normalised',
            'found' => 'An address with capital letters answers 200.',
            'why' => 'A link typed in another case creates a duplicate.',
            'fix' => 'Redirect addresses with capitals to the lowercase one with a 301.',
        ],
        'soft_404' => [
            'title' => 'Missing pages do not answer 404',
            'found' => 'An address that cannot exist answers 200 or redirects.',
            'why' => 'Search engines index typos and deleted pages as real pages.',
            'fix' => 'Answer 404 for unknown addresses; do not redirect them to the home page.',
        ],
        '404_page' => [
            'title' => 'The 404 page leads nowhere',
            'found' => 'The 404 page has no link to the home page.',
            'why' => 'A visitor who followed a broken link has nowhere to go.',
            'fix' => 'Add a link to the home page, the search or the main sections to the 404 template.',
        ],
        'compression' => [
            'title' => 'HTML without compression',
            'found' => 'Pages are sent without gzip or brotli.',
            'why' => 'Pages weigh several times more and open slower, especially on mobile.',
            'fix' => 'Turn on gzip or brotli for text/html in the web server.',
        ],
        'security_headers' => [
            'title' => 'Security headers are missing',
            'found' => 'Some of X-Content-Type-Options, Referrer-Policy and framing protection are missing.',
            'why' => 'They close cheap attacks: MIME sniffing, leaking addresses, clickjacking.',
            'fix' => 'Add the headers in the web server: nosniff, strict-origin-when-cross-origin, SAMEORIGIN.',
        ],
        'server_leak' => [
            'title' => 'The server tells its versions',
            'found' => 'X-Powered-By, or Server with a version number.',
            'why' => 'A ready map for whoever looks for a known hole in that version.',
            'fix' => 'Turn off expose_php and server_tokens (or their equivalents).',
        ],
        'static_cache' => [
            'title' => 'Static files are not cached',
            'found' => 'CSS, JS or pictures without Cache-Control or cached for less than a week.',
            'why' => 'Every page downloads them again.',
            'fix' => 'Give versioned static files a long Cache-Control (a year, immutable) in the web server.',
        ],
    ],
    'hosts' => [
        'dev_content' => [
            'title' => 'Links to a development stand in the content',
            'found' => 'An address of a development stand in a record — published, in a draft, or in a field the template does not print.',
            'why' => 'Content filled in on a stand goes live with links and pictures pointing back at the stand; visitors get errors, and the stand gets indexed.',
            'fix' => 'Open the record and replace the stand address with the site’s own or with a relative link. List the stands in the audit settings so all of them are caught.',
        ],
    ],
];
