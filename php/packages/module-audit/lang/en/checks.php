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
        'dev_page' => [
            'title' => 'Links to a development stand on the page',
            'found' => 'A link or a resource of the page — a picture, a script, a style, og:image, the canonical — leads to a development stand.',
            'why' => 'Visitors follow links to a site that is not meant for them, pictures break when the stand goes down, and search engines find the stand through the site.',
            'fix' => 'Find the address in the page’s content or template and replace the stand with the site’s own host or with a relative link.',
        ],
        'similar' => [
            'title' => 'A host that looks like this site',
            'found' => 'A link to a host with the same first word as the site in another zone, such as shop.local next to shop.com.',
            'why' => 'It is most likely a stand or an old copy of the site that the audit does not know about.',
            'fix' => 'If it is a stand or an old domain, add it to “Other addresses of this site” in the audit settings and fix the links; if it is somebody else’s site, nothing needs doing.',
        ],
        'wrong_mirror' => [
            'title' => 'Links through another mirror',
            'found' => 'A link to the site through its other mirror (www or the bare name) or over http on an https site.',
            'why' => 'Every click goes through a redirect: slower for visitors, and search engines see links to an address that is not the page.',
            'fix' => 'Link to the main mirror over https, or use relative links.',
        ],
        'absolute_own' => [
            'title' => 'Absolute links to the site itself',
            'found' => 'A link or a picture in the content is written with the site’s own host instead of a path.',
            'why' => 'It works today and breaks at the next move to another domain or protocol, and copied to a stand it leads back to the live site.',
            'fix' => 'Write links to the site’s own pages as paths: /about instead of https://shop.com/about.',
        ],
        'new_domain' => [
            'title' => 'A new outside domain',
            'found' => 'The site links to a domain it did not link to in the previous full run.',
            'why' => 'A new domain is usually a new link someone added — and sometimes a typo, or spam links left by somebody who got into the site.',
            'fix' => 'Open the pages listed and make sure the link is meant to be there.',
        ],
        'external_many' => [
            'title' => 'Many external links on a page',
            'found' => 'The page has more external links than the threshold.',
            'why' => 'A page that is mostly links to other sites looks like a link farm to search engines, and is often a sign of spam.',
            'fix' => 'Remove links that do not help the visitor, or split the page.',
        ],
        'blank_opener' => [
            'title' => 'New tab without noopener',
            'found' => 'A link to another site opens in a new tab without rel="noopener".',
            'why' => 'In older browsers the opened page can redirect the site’s tab to a page of its choosing.',
            'fix' => 'Add rel="noopener" (or noreferrer) to links with target="_blank".',
        ],
    ],
    'indexing' => [
        'home_noindex' => [
            'title' => 'The home page is closed to search engines',
            'found' => 'The home page has noindex in the robots meta tag or in the X-Robots-Tag header.',
            'why' => 'The most important page of the site drops out of search, and often the whole site follows.',
            'fix' => 'Remove noindex from the home page: check the SEO settings of the page, the layout and the web server headers.',
        ],
        'noindex' => [
            'title' => 'Pages closed with noindex',
            'found' => 'The page has noindex in the robots meta tag or in the X-Robots-Tag header.',
            'why' => 'Search engines drop the page. Right for search results and service pages, wrong for content that was closed by mistake.',
            'fix' => 'Look through the list; open the pages that should be found in their SEO settings and remove noindex.',
        ],
    ],
    'title' => [
        'missing' => [
            'title' => 'No title',
            'found' => 'The page has no <title>, or it is empty.',
            'why' => 'The title is the line search engines show as the link to the page; without it they invent one.',
            'fix' => 'Give the page a title in its SEO settings, or check that the layout prints one.',
        ],
        'duplicate' => [
            'title' => 'Duplicate titles',
            'found' => 'Several indexable pages have the same title.',
            'why' => 'Search engines cannot tell the pages apart and show one of them, not necessarily the right one.',
            'fix' => 'Give each page a title of its own that says what is on it.',
        ],
        'length' => [
            'title' => 'Title too short or too long',
            'found' => 'The title is shorter or longer than the thresholds, in characters.',
            'why' => 'A long title is cut in search results (the limit is about 600 pixels, roughly 60 characters); a short one says too little.',
            'fix' => 'Rewrite the title to fit the range in the audit thresholds.',
        ],
        'multiple' => [
            'title' => 'More than one title',
            'found' => 'The page has more than one <title> tag.',
            'why' => 'Search engines take one of them, not necessarily the one written for the page.',
            'fix' => 'Find which template or block prints the second title and remove it.',
        ],
    ],
    'description' => [
        'missing' => [
            'title' => 'No meta description',
            'found' => 'The page has no meta description, or it is empty.',
            'why' => 'Search engines make up the snippet under the link from whatever text they find.',
            'fix' => 'Write a description in the page’s SEO settings: what the page offers, in a sentence or two.',
        ],
        'duplicate' => [
            'title' => 'Duplicate descriptions',
            'found' => 'Several indexable pages have the same meta description.',
            'why' => 'The same snippet under different links tells the searcher nothing, and search engines replace it with their own.',
            'fix' => 'Write a description of its own for each page.',
        ],
        'length' => [
            'title' => 'Description too short or too long',
            'found' => 'The description is shorter or longer than the thresholds, in characters.',
            'why' => 'A long description is cut in search results (about 920 pixels, roughly 160 characters); a short one is often replaced.',
            'fix' => 'Rewrite the description to fit the range in the audit thresholds.',
        ],
    ],
    'h1' => [
        'missing' => [
            'title' => 'No H1',
            'found' => 'The page has no H1 heading.',
            'why' => 'The H1 tells visitors and search engines what the page is about; screen readers use it to find the start of the content.',
            'fix' => 'Give the page one H1 — usually its name — in the template or the content.',
        ],
        'multiple' => [
            'title' => 'More than one H1',
            'found' => 'The page has more than one H1 heading.',
            'why' => 'Not an error by itself, but usually a sign that a block or the logo uses H1 where a lower level was meant.',
            'fix' => 'Keep one H1 for the name of the page and make the others H2 or lower.',
        ],
        'equals_title' => [
            'title' => 'H1 the same as the title',
            'found' => 'The H1 repeats the title word for word.',
            'why' => 'Two places to describe the page say the same thing; one of them could add a word that people search for.',
            'fix' => 'Keep the H1 short and readable, and let the title carry the keywords and the site’s name.',
        ],
    ],
    'headings' => [
        'skipped' => [
            'title' => 'Skipped heading level',
            'found' => 'A heading level is skipped on the way down, such as H2 followed by H4.',
            'why' => 'Screen readers navigate by headings, and a gap reads as missing content.',
            'fix' => 'Use the levels in order; choose the look with styles, not with the level.',
        ],
    ],
    'canonical' => [
        'missing' => [
            'title' => 'No canonical',
            'found' => 'An indexable page has no canonical link, in the tag or in the header.',
            'why' => 'Without it, every copy of the page with tracking or sorting parameters can compete with the page itself.',
            'fix' => 'Have the layout print <link rel="canonical"> with the page’s own address.',
        ],
        'relative' => [
            'title' => 'Relative canonical',
            'found' => 'The canonical is written as a path, not as a full address.',
            'why' => 'Search engines read it against whatever address they came by, including another mirror or protocol.',
            'fix' => 'Print the canonical as a full address with the scheme and the main host.',
        ],
        'multiple' => [
            'title' => 'Conflicting canonicals',
            'found' => 'The page has more than one canonical, or the tag and the Link header disagree.',
            'why' => 'With conflicting canonicals, search engines ignore all of them.',
            'fix' => 'Leave one canonical: find the template, block or server rule that adds the second and remove it.',
        ],
        'broken' => [
            'title' => 'Canonical to a broken or closed page',
            'found' => 'The canonical leads to a redirect, an error or a page with noindex.',
            'why' => 'The page names an original that cannot be indexed, and search engines may drop both.',
            'fix' => 'Point the canonical at the page’s own working address, or at the live original.',
        ],
        'other' => [
            'title' => 'Canonical to another page',
            'found' => 'The canonical points to a different address than the page’s own.',
            'why' => 'The page asks not to be indexed in favour of another — right for filters and copies, wrong for a page meant to be found.',
            'fix' => 'Look through the list; for pages that should be found, make the canonical their own address.',
        ],
    ],
    'html' => [
        'lang' => [
            'title' => 'No page language',
            'found' => 'The <html> tag has no lang attribute.',
            'why' => 'Screen readers pick the voice by it, browsers offer translation by it, and search engines use it as a hint.',
            'fix' => 'Print <html lang="…"> with the language of the page in the layout.',
        ],
        'viewport' => [
            'title' => 'No meta viewport',
            'found' => 'The page has no <meta name="viewport">.',
            'why' => 'Phones draw the page at desktop width, shrunk; search engines treat such a page as not mobile-friendly.',
            'fix' => 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> to the layout.',
        ],
        'favicon' => [
            'title' => 'No icon',
            'found' => 'The page links no icon.',
            'why' => 'Browser tabs, bookmarks and search results on phones show an empty square instead of the site’s mark.',
            'fix' => 'Add <link rel="icon"> to the layout.',
        ],
    ],
    'og' => [
        'missing' => [
            'title' => 'Open Graph tags missing',
            'found' => 'The page has no og:title, og:image or og:url.',
            'why' => 'A link shared in a messenger or a social network shows as a bare address without a picture or a title.',
            'fix' => 'Fill in the social preview in the page’s SEO settings, or have the layout print the tags.',
        ],
    ],
    'content' => [
        'thin' => [
            'title' => 'Little text',
            'found' => 'An indexable page has fewer words than the threshold.',
            'why' => 'Search engines rank pages with little to read lower, and may treat many such pages as low quality.',
            'fix' => 'Add text that helps the visitor, merge thin pages, or close them with noindex.',
        ],
        'text_ratio' => [
            'title' => 'Little text for the markup',
            'found' => 'The visible text is a smaller share of the HTML than the threshold.',
            'why' => 'The page is heavy for what it says: slow on a phone, and search engines find little content in a lot of code.',
            'fix' => 'Move inline scripts and styles to files, remove unused markup, and add content.',
        ],
        'duplicate' => [
            'title' => 'Duplicate text',
            'found' => 'Several indexable pages have the same visible text.',
            'why' => 'Search engines choose one copy to show and ignore the rest.',
            'fix' => 'Make the pages different, merge them, or point the copies’ canonical at the original.',
        ],
    ],
    'url' => [
        'length' => [
            'title' => 'Long address',
            'found' => 'The address is longer than the threshold.',
            'why' => 'Long addresses are cut in search results and hard to share and read.',
            'fix' => 'Shorten the slug of the page; a redirect from the old address is added automatically.',
        ],
        'format' => [
            'title' => 'Address format',
            'found' => 'The path has capital letters, underscores or characters outside ASCII.',
            'why' => 'Capitals make /About and /about two pages, underscores do not separate words for search engines, and other characters turn into %D0%B0 when copied.',
            'fix' => 'Use lowercase Latin letters, digits and hyphens in slugs.',
        ],
        'params' => [
            'title' => 'Parameters without canonical',
            'found' => 'An indexable address has query parameters and no canonical.',
            'why' => 'Every combination of filters and sorting becomes a page of its own in search engines, and they split the weight of the real one.',
            'fix' => 'Print a canonical to the address without parameters, or close such addresses with noindex.',
        ],
    ],
    'perf' => [
        'ttfb' => [
            'title' => 'Slow answer',
            'found' => 'The page took longer than the threshold to answer.',
            'why' => 'Visitors wait before anything appears, and search engines crawl a slow site less.',
            'fix' => 'Turn on the caches (config, routes, views, pages), check slow queries, and move heavy work to the queue.',
        ],
        'html_size' => [
            'title' => 'Heavy HTML',
            'found' => 'The HTML of the page is larger than the threshold.',
            'why' => 'Phones download and parse it slowly; search engines may stop reading before the end.',
            'fix' => 'Paginate long lists, move inline data and SVG to files, remove hidden copies of the content.',
        ],
    ],
    'links' => [
        'broken' => [
            'title' => 'Broken internal links',
            'found' => 'A link to the site’s own page answers 4xx, 5xx or nothing.',
            'why' => 'Visitors land on an error, and search engines waste their visit on it.',
            'fix' => 'Fix or remove the link, or add a redirect from the missing address to the right page.',
        ],
        'empty' => [
            'title' => 'Links without text',
            'found' => 'A link has no text, no aria-label, and a picture link has no alt.',
            'why' => 'Screen readers read out the address or just “link”, and search engines learn nothing about the page it leads to.',
            'fix' => 'Give the link text, an aria-label, or an alt to its picture.',
        ],
        'nofollow_internal' => [
            'title' => 'nofollow on internal links',
            'found' => 'A link to the site’s own page has rel="nofollow".',
            'why' => 'The site asks search engines not to follow its own links, and the page gets less weight.',
            'fix' => 'Remove nofollow from links to the site’s own pages.',
        ],
    ],
    'mixed_content' => [
        'title' => 'Mixed content',
        'found' => 'An https page loads a resource over http.',
        'why' => 'Browsers block such scripts and styles and warn about pictures; the padlock disappears.',
        'fix' => 'Load the resource over https, or use a path without the scheme.',
    ],
    'forms' => [
        'insecure' => [
            'title' => 'Form sent over http',
            'found' => 'A form is submitted to an http address.',
            'why' => 'What visitors type travels unencrypted, and browsers warn before sending it.',
            'fix' => 'Point the form at an https address or at a path.',
        ],
    ],
    'images' => [
        'alt' => [
            'title' => 'Pictures without alt',
            'found' => 'An <img> has no alt attribute.',
            'why' => 'Screen readers read out the file name, and search engines do not know what the picture shows. An empty alt for a decorative picture is fine.',
            'fix' => 'Describe the picture in its alt, or set alt="" if it is decoration.',
        ],
        'dimensions' => [
            'title' => 'Pictures without size',
            'found' => 'An <img> has no width and height.',
            'why' => 'The page jumps while pictures load, and visitors click the wrong thing.',
            'fix' => 'Print width and height of pictures in the template; CSS can still make them responsive.',
        ],
    ],
    'a11y' => [
        'button_name' => [
            'title' => 'Buttons without a name',
            'found' => 'A button has no text, aria-label or title.',
            'why' => 'A screen reader can only say “button”, and its user does not know what it does.',
            'fix' => 'Give the button text, or an aria-label if it is an icon.',
        ],
        'form_label' => [
            'title' => 'Fields without a label',
            'found' => 'A form field has no label and no aria-label.',
            'why' => 'A screen reader cannot say what to type; a placeholder disappears as soon as typing starts.',
            'fix' => 'Add a <label for="…"> to each field, or an aria-label.',
        ],
        'iframe_title' => [
            'title' => 'Frames without a title',
            'found' => 'An iframe has no title.',
            'why' => 'Screen readers announce an unnamed frame, and their users cannot tell a map from a video.',
            'fix' => 'Add a title that says what the frame shows.',
        ],
    ],
    'structure' => [
        'depth' => [
            'title' => 'Deep pages',
            'found' => 'An indexable page is further from the home page than the threshold, in clicks.',
            'why' => 'Search engines visit deep pages less often and value them less; visitors rarely get there.',
            'fix' => 'Link to the page from a category, the menu or related pages.',
        ],
        'orphan' => [
            'title' => 'Orphan pages',
            'found' => 'The page is in the sitemap or the address registry, but no page of the site links to it.',
            'why' => 'Visitors cannot reach it, and search engines treat a page nothing links to as unimportant.',
            'fix' => 'Link to the page from where it belongs, or unpublish it if it is not needed.',
        ],
        'dead_end' => [
            'title' => 'Dead ends',
            'found' => 'The page links to no other page of the site.',
            'why' => 'A visitor who lands on it has nowhere to go but back.',
            'fix' => 'Check that the layout with its menu is used, and add links to related pages.',
        ],
    ],
];
