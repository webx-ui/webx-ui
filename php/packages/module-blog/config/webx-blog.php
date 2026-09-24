<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | The first segment of every blog address
    |---------------------------------------------------------------------------
    |
    | An article is at `blog/how-to-choose`, a rubric at `blog/repairs`, a tag at
    | `blog/tag/belts`. The prefix belongs to the module rather than to routing:
    | deciding that articles live under `/blog/` is the same kind of choice as
    | deciding they are spelled with the slug alone.
    |
    | Empty puts all three at the root of the site, beside the pages — the address
    | space is flat either way, so an article and a page can never share a spelling.
    | The feed route is then not registered at all: `/` belongs to the site, and a
    | list of articles on it is a page the site writes.
    |
    | Changing this afterwards is `php artisan webx:routes:rebuild --type=article
    | --type=rubric --type=tag`: the paths are recomputed and the old ones stay
    | behind as aliases that redirect. Technically one command; to a search engine,
    | the whole blog moving house.
    |
    */

    'prefix' => env('WEBX_BLOG_PREFIX', 'blog'),

    /*
    |---------------------------------------------------------------------------
    | How many articles a listing page holds
    |---------------------------------------------------------------------------
    |
    | The feed, a rubric and a tag all use it, and page two is `?page=2` rather
    | than a second address: a listing in the registry would be two hundred rows
    | where one belongs, and the resolver drops the query string anyway.
    |
    */

    'per_page' => 12,

    /*
    |---------------------------------------------------------------------------
    | How many related articles are worked out
    |---------------------------------------------------------------------------
    |
    | The ones pinned by hand come first and always; this is how many are filled
    | in under them — most tags in common, then the main rubric, published only.
    | Zero turns the automatic half off and leaves the pinned list alone.
    |
    */

    'related' => 3,

    /*
    |---------------------------------------------------------------------------
    | Tags
    |---------------------------------------------------------------------------
    |
    | A tag page has an address, because people follow tags; what it does not get
    | by default is a place in the index, because a hundred thin listings is how a
    | site teaches a search engine to ignore it. The flag is per tag and this is
    | only the value a new one starts with.
    |
    | A rule in `seo_urls` for a tag's address overrides the flag and opens the
    | page completely: writing the rule is the decision that this page is wanted.
    |
    */

    'tags' => [
        'noindex' => true,
    ],

    /*
    |---------------------------------------------------------------------------
    | The views the public half is printed with
    |---------------------------------------------------------------------------
    |
    | A site keeps its layout, its header and its footer here. Until it has written
    | them, the package's own are used — bare documents that serve a blog rather
    | than an error on a fresh installation.
    |
    | `$article`, or `$articles` (a paginator) with `$rubric` / `$tag` beside it,
    | is what each one is handed.
    |
    */

    'views' => [
        'article' => env('WEBX_BLOG_VIEW_ARTICLE', 'blog.article'),
        'feed' => env('WEBX_BLOG_VIEW_FEED', 'blog.feed'),
        'rubric' => env('WEBX_BLOG_VIEW_RUBRIC', 'blog.rubric'),
        'tag' => env('WEBX_BLOG_VIEW_TAG', 'blog.tag'),
        'rss' => env('WEBX_BLOG_VIEW_RSS', 'blog.rss'),
    ],

    /*
    |---------------------------------------------------------------------------
    | The layout the four public pages stand in
    |---------------------------------------------------------------------------
    |
    | The name of a Blade component: `'layout'` for the `<x-layout>` a site keeps
    | in `resources/views/components/layout.blade.php`. Empty prints the package's
    | own `webx-blog::standalone` — a bare document, which is the right default
    | for a package that cannot assume the site has a layout at all.
    |
    | The deal is two slots and no more: `head`, and the default slot for the
    | content. A layout is expected to carry `@stack('head')` beside `{{ $head }}`
    | as well — a slot is one place, and what a block type pushes cannot reach it.
    | `php artisan webx:doctor` says so if it does not.
    |
    | This is not the same seam as `views` above: those replace the markup of a
    | page, this one only says what it stands in. The RSS route has no layout: it
    | is a feed, not a page.
    |
    */

    'layout' => env('WEBX_BLOG_LAYOUT'),

    /*
    |---------------------------------------------------------------------------
    | The trail above the content
    |---------------------------------------------------------------------------
    |
    | Whether the package's views print the crumbs a reader sees on an article, a rubric and a tag.
    | Many sites want them in the blog and nowhere else, so this is a switch per
    | module rather than one for the whole site.
    |
    | Only the visible trail: the BreadcrumbList in the <head> is `module-seo`'s
    | (`webx-seo.print.breadcrumbs`) and stays, because it helps a search engine
    | whether or not the page shows it. A site with its own `views` above
    | decides in its own markup and never reads this.
    |
    */

    'breadcrumbs' => (bool) env('WEBX_BLOG_BREADCRUMBS', true),

    /*
    |---------------------------------------------------------------------------
    | What the feed and the RSS route run through
    |---------------------------------------------------------------------------
    |
    | The same list the address registry answers through, because they are the same
    | kind of page: a session, and the decision about which language this request
    | is in. Named here rather than read from `webx-routing` at boot so that a site
    | can give the blog its own stack without moving everything else onto it.
    |
    */

    'middleware' => ['web', 'webx.locale'],

];
