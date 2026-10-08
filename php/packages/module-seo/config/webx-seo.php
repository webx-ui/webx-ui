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
    | (`HasStructuredData`, `Seo::push()`). `og` is every og:* line, `article`
    | the article:* ones of an article (dates, section, tags), `twitter` the
    | twitter:* lines. X reads og:* when twitter:* is missing, so those mirror
    | it and can be turned off; they are on because some readers do not fall back.
    |
    */

    'print' => [
        'title' => true,
        'description' => true,
        'keywords' => true,
        'robots' => true,
        'canonical' => true,
        'og' => true,
        'article' => true,
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
    | Filled in from what the page already says, never typed for the purpose:
    | og:title, og:description and og:url are the title (after the template),
    | the description and the canonical; og:image is the first picture of the
    | sources a network can show (an SVG falls through to the next one), with
    | its type, size and alt. og:type is the entity's (HasOpenGraph) or `type`.
    |
    | `locales` maps a language code to the og:locale it means, where the
    | default guess (en → en_US, de → de_DE) is not the one: ['pt' => 'pt_BR'].
    |
    | `image` is the variant a landscape library picture at least that big is
    | cut to — the size networks recommend; null shares the picture as it is.
    |
    | `panel_fields` brings back the share title, description and picture in
    | the SEO card, for a site that wants to override them by hand. Off, values
    | already stored are still honoured.
    |
    */

    'og' => [
        'type' => 'website',
        'locales' => [],
        'image' => ['width' => 1200, 'height' => 630],
        'panel_fields' => env('WEBX_SEO_OG_FIELDS', false),
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
    | Fallbacks are what an entity says without a card — its own name, lead and
    | picture (HasSeoFallback). Below the card, above the defaults: a recipe's
    | photo is a better picture of the recipe than the site's default one.
    |
    */

    'sources' => [
        'urls' => 100,
        'entities' => 50,
        'fallbacks' => 30,
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
    | `stylesheet` serves `/sitemap.xsl` and names it in every file, so a browser
    | shows the map as its own text with the addresses clickable. Crawlers ignore it.
    |
    */

    'sitemap' => [
        'enabled' => env('WEBX_SEO_SITEMAP', true),
        'stylesheet' => env('WEBX_SEO_SITEMAP_XSL', true),
        'per_file' => 45000,
        'cache' => [
            'enabled' => true,
            'ttl' => (int) env('WEBX_SEO_SITEMAP_TTL', 86400),
            'key' => 'webx.seo.sitemap',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Interlinking and page FAQ
    |---------------------------------------------------------------------------
    |
    | Two tools of an SEO brief, both off until a developer turns them on for a
    | project — a decision of the brief, not a button for an administrator. Off,
    | a feature is gone entirely: no view in the panel, no API, no MCP tools, and
    | its Blade component prints nothing. Its tables migrate either way, so
    | turning it off never loses what was written.
    |
    */

    'links' => [
        'enabled' => env('WEBX_SEO_LINKS', false),
    ],

    'faq' => [
        'enabled' => env('WEBX_SEO_FAQ', false),
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
