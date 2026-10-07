# webx-ui/module-seo

SEO as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel, and the
`<head>` the public side prints from it.

What it owns is everything a page can say about itself: the rules written for addresses, the
fields of one entity (`HasSeo` and `seo_meta`), what the site says when nothing more specific
does, and the addresses that have moved. Each of those is a source, asked in order and merged
field by field, so a project adds one of its own without touching the resolver.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-auth`, `webx-ui/module-settings`, `webx-ui/localization`,
  `webx-ui/mcp`, `webx-ui/routing`

## Install

```bash
composer require webx-ui/module-seo
php artisan migrate
```

The section appears once the front end lists it too: `seo()` from `@webx-ui/module-seo` in
`createAdmin({ modules: [...] })`. Permissions: `seo.view`, `seo.manage`.

## Printing the head

```blade
<head>
    @webxSeo
</head>
```

With an entity to name — once there is a source that answers about one:

```blade
@webxSeo($page)
{{-- or --}}
<x-webx-seo::head :for="$page" />
```

Both print `<title>`, the description, keywords and robots meta tags, the canonical link, the
Open Graph properties and every JSON-LD block. What each of those is allowed to print is in
`config/webx-seo.php`.

## Where a value comes from

Sources are asked highest first and merged **field by field**, so a rule that fills in nothing
but a title keeps the description and the picture that came from below it.

| Priority | Source           | Reads                                                   |
| -------- | ---------------- | ------------------------------------------------------- |
| 100      | `UrlRuleSource`  | `seo_urls` — the rules written for addresses            |
| 50       | `EntitySource`   | `seo_meta` — the entity's SEO card                      |
| 30       | `FallbackSource` | the entity's own name, lead and picture (`seoFallback`) |
| 10       | `DefaultsSource` | `settings('seo.*')`                                     |

A project adds its own by implementing `SeoSource` and registering it:

```php
app(SeoSources::class)->register(new MySource);
```

## An entity that speaks for itself

```php
use WebxUi\Seo\HasSeo;

class Page extends Model
{
    use HasSeo;
}
```

One row of `seo_meta` per entity, translated. `$page->seoValue()` and `$page->saveSeo()` read
and write it, `$page->seoData($locale)` is what it contributes to the `<head>`. An entity with
no row contributes nothing, which is what lets the site's defaults through. The card itself is
one node of type `wx-seo`, put on the entity's screen by a patch.

## Matching an address

One matcher serves rules and redirects alike. What is compared is the path with its query
string, as it arrived — `/catalog/shoes?page=2` — with a trailing slash removed from anything but
the root.

- `exact` — that address and no other.
- `mask` — `*` is a stretch without a slash, `**` is a stretch with them. A redirect target may
  name what was captured: `/catalog/*` → `/shop/$1`.
- `regex` — stored as written, delimiters and all, and checked when it is saved. A pattern that
  will not compile never matches; it cannot take the public side down.

Rules are tried exact first, then mask, then regex; inside a group by `priority` descending. The
first match wins. The active ones are kept as one compiled list in the cache, thrown away
whenever any of them is saved or deleted.

An exact rule remembers the entity behind its address when it is saved (an old address of a
page binds to that page; an address a redirect catches is replaced by its target), and matches
wherever the entity is now — renaming a category does not lose its rule. Masks, regular
expressions and addresses with a query stay plain patterns.

## Interlinking

Off until a project turns it on: `WEBX_SEO_LINKS=true` (`webx-seo.links.enabled`). Off, there is
no API, no MCP tools and no view in the panel; the tables are there either way.

A donor page owns a block — a heading and an ordered list of acceptor and anchor. Put the
component where the block belongs; it prints nothing on a page without one:

```blade
<x-webx-seo::links />
```

The markup is the `webx-seo::links` view (`<nav aria-labelledby>`, publish `webx-seo-views` to
restyle). Links whose page is gone or hidden are left out. A block without a heading takes the
`seo.links-heading` setting, then the module's own "See also".

A brief comes in as CSV or XLSX — columns `donor`, `acceptor`, `anchor` and an optional
`heading` (Russian headers work too) — previewed first, with errors per row, then replacing the
blocks of the donors in the file or appending to them. The export writes the same file.

## The sitemap

`/sitemap.xml` is an index with a file per type of the address registry, plus `sitemap-routes.xml`
for named routes a module registered (`SitemapRoutes`) and a file per `SitemapSource`. It has no
rules of its own: an address is in it when the registry has a canonical row, the entity is
`Visible`, and the `<head>` would print neither `noindex` nor a foreign canonical. Settings are
under `sitemap` in `config/webx-seo.php`; `php artisan webx:seo:sitemap` builds it ahead of time.

A type whose handler never shows a page is left out whole. A site that binds its own handler over a
module's (one that redirects every event to its booking page, say) marks the class with
`WebxUi\Routing\Contracts\NotAPage`:

```php
use WebxUi\Routing\Contracts\NotAPage;
use WebxUi\Routing\RouteHandler;

final class EventRedirect implements RouteHandler, NotAPage
{
    public function handle(Request $request, object $entity, string $tail): Response
    {
        return redirect()->away($entity->booking_url, 301);
    }
}

// in the site's provider
$this->app->bind(EventHandler::class, EventRedirect::class);
```

The type gets no file and is not in the index. `GET /sitemap` and `seo_sitemap_status` list it
under `excluded_types` (`type`, `reason: not-a-page`, `handler`, `addresses`), and `test-url` says
`not-a-page` for its addresses. The built map is cached under the set of such types, so a deploy
that changes the binding needs no rebuild. A type without the marker behaves as before.

## `robots` and `robots.txt` are different things

- `robots` on a rule is that page's own meta directives — `noindex, nofollow` — printed into
  `<meta name="robots">`.
- `seo.robots-txt` is one setting for the whole site, served at `/robots.txt`.

If a real `public/robots.txt` exists, the web server hands it over before Laravel is asked, and
the setting never gets a say. That is the server doing its job, not a bug in this package.

## The settings tab

The module lays a patch over `settings.index`. The ids below are a contract: a project writes its
own patch against them.

| id               | type          | name                 |
| ---------------- | ------------- | -------------------- |
| `seo`            | `wx-tab`      |                      |
| `seo-card`       | `wx-card`     |                      |
| `default-og`     | `wx-media`    | `seo.default-og`     |
| `title-template` | `wx-input`    | `seo.title-template` |
| `robots-txt`     | `wx-textarea` | `seo.robots-txt`     |
| `org-name`       | `wx-input`    | `seo.org-name`       |
| `org-logo`       | `wx-media`    | `seo.org-logo`       |
| `org-socials`    | `wx-repeater` | `seo.org-socials`    |

The dot in `seo.default-og` is part of the name, not a path.

## API

Under `config('webx-admin.api_path').'/seo'`:

| Method and address                        | What it does                                                |
| ----------------------------------------- | ----------------------------------------------------------- |
| `GET /urls`                               | the rules, in the order the site tries them                 |
| `POST /urls`                              | add one                                                     |
| `GET`, `PUT`, `DELETE` on `/urls/{id}`    | one rule                                                    |
| `GET /redirects` …                        | the same for redirects                                      |
| `GET /aliases`                            | the addresses renames left behind; read only                |
| `POST /test-url`                          | what an address ends up saying, and where each part is from |
| `GET`, `POST /links`                      | interlinking donors (only with the feature on)              |
| `GET`, `PUT`, `DELETE` on `/links/{id}`   | one donor's block                                           |
| `POST /links/import`, `GET /links/export` | the brief in and out; import previews unless `dry_run=0`    |
| `POST /links/heading`                     | one heading on picked donors or on an address prefix        |

`test-url` is the one worth remembering: it answers "why does this page have the wrong title" in
one call — and says what `webx-ui/routing` holds at the address, which is how the panel warns that
a redirect is about to shadow a live page.

`/aliases` is the other half of the redirects: the trail a rename leaves in the address registry,
read through its `RouteAliases` contract. Nothing writes there — those rows belong to the entity
that moved.

## Structured data

Three levels, on purpose:

- **About the site** — `Organization`, `WebSite` — comes from the settings fields, and the JSON-LD
  is assembled from them.
- **About an entity** — `Article`, `Product`, `BreadcrumbList` — is generated from the entity by
  the module that owns it. Typed in by hand it drifts away from the page within a month.
- **One-off exceptions** — the JSON-LD field on a rule.

There is no schema.org builder in the panel and there will not be one: the vocabulary has
hundreds of types and filling them in by hand is how markup starts lying.

## License

MIT.
