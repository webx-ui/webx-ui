<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The title template
    |---------------------------------------------------------------------------
    |
    | What the `seo.title-template` setting starts out as, and what is used when
    | an administrator empties it. `{title}` is the page's own title, `{site}` is
    | the project name from the settings; a placeholder with nothing behind it
    | takes its separator with it rather than leaving "Contacts —" on the page.
    |
    */

    'title_template' => '{title}',

    /*
    |---------------------------------------------------------------------------
    | What the <head> prints
    |---------------------------------------------------------------------------
    |
    | A site that writes its own canonical links, or feeds Open Graph from
    | somewhere else, turns that block off here rather than working around it.
    |
    | `hreflang` is the page in the site's other languages, `breadcrumbs` the
    | BreadcrumbList, `structured_data` what the entity and the handler add
    | (`HasStructuredData`, `Seo::push()`), `twitter` the one `twitter:card` line.
    |
    */

    'print' => [
        'title' => true,
        'description' => true,
        'keywords' => true,
        'robots' => true,
        'canonical' => true,
        'og' => true,
        'json_ld' => true,
        'hreflang' => true,
        'breadcrumbs' => true,
        'structured_data' => true,
        'twitter' => true,
    ],

    /*
    |---------------------------------------------------------------------------
    | Lengths
    |---------------------------------------------------------------------------
    |
    | Soft: the panel counts a field against these and says when it is long, and
    | that is all they do unless `trim` is on. Cutting an editor's title behind
    | their back is the kind of help that reads as a bug, so it is opt-in.
    |
    */

    'limits' => [
        'title' => 60,
        'description' => 160,
        'keywords' => 255,
    ],

    'trim' => false,

    /*
    |---------------------------------------------------------------------------
    | Open Graph
    |---------------------------------------------------------------------------
    |
    | `og:title`, `og:description` and `og:url` fall back to the title, the
    | description and the canonical address when nothing sets them apart.
    |
    */

    'og' => [
        'type' => 'website',
    ],

    /*
    |---------------------------------------------------------------------------
    | Sources
    |---------------------------------------------------------------------------
    |
    | Who gets asked first. A source answers with the fields it knows and leaves
    | the rest alone: a rule that fills in only a title does not wipe out the
    | description that came from the defaults below it.
    |
    | The entity sits between the two: a rule was written because a page was
    | wrong, so it wins; the defaults are what is said when nothing was said,
    | so they lose.
    |
    */

    'sources' => [
        'urls' => 100,
        'entities' => 50,
        'defaults' => 10,
    ],

    /*
    |---------------------------------------------------------------------------
    | Redirects
    |---------------------------------------------------------------------------
    |
    | The middleware answers before the router does, so a redirect works for an
    | address the site has no route for — which is most of them, since the
    | addresses worth redirecting are the ones that stopped existing.
    |
    */

    'redirects' => [
        'enabled' => env('WEBX_SEO_REDIRECTS', true),
    ],

    /*
    |---------------------------------------------------------------------------
    | robots.txt
    |---------------------------------------------------------------------------
    |
    | The route answers with the `seo.robots-txt` setting. With the setting
    | empty it answers 404, so a file somebody put in `public/` keeps working.
    | That file wins anyway: the web server hands it over before PHP is asked.
    |
    */

    'robots_txt' => [
        'enabled' => true,
    ],

    /*
    |---------------------------------------------------------------------------
    | Canonical
    |---------------------------------------------------------------------------
    |
    | A page nobody wrote a canonical for names itself, so that `?utm_source=`
    | and every other tracking tail collapse into the one address. What survives
    | of the query is listed here: pagination is a page of its own, the rest is
    | noise. A canonical written in a card or a rule always wins.
    |
    */

    'canonical' => [
        'self' => true,
        'query' => ['page'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Sitemap
    |---------------------------------------------------------------------------
    |
    | `/sitemap.xml` and a file per type of the address registry. What goes in is
    | what the <head> of the page would leave open to the index — no rules of the
    | map's own. A site with a sitemap of its own turns this off and keeps it.
    |
    | Built on the first request and kept until anything it depends on is saved;
    | the TTL is for what changes without a save, like an article whose date has
    | come. `php artisan webx:seo:sitemap` builds it ahead of the first crawler.
    |
    */

    'sitemap' => [
        'enabled' => env('WEBX_SEO_SITEMAP', true),
        'per_file' => 45000,
        'cache' => [
            'enabled' => true,
            'ttl' => (int) env('WEBX_SEO_SITEMAP_TTL', 86400),
            'key' => 'webx.seo.sitemap',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Cache
    |---------------------------------------------------------------------------
    |
    | Every hit on the public side matches the address against every rule, so
    | the rules are kept as one compiled list and thrown away when any of them
    | is saved or deleted.
    |
    */

    'cache' => [
        'enabled' => env('WEBX_SEO_CACHE', true),
        'ttl' => 86400,
        'key' => 'webx.seo.rules',
    ],

];
