# SEO

`@webx-ui/module-seo` is the section where a site says what its pages are called, where its old
addresses went, and what it says about itself. Its other half, `webx-ui/module-seo` on the server,
is what actually prints that into a `<head>` — this page is both, because neither is useful alone.

What the module owns is everything a page can say about itself: rules written for addresses, the
fields of an entity that carries `HasSeo`, the site-wide defaults, and the redirects. Each of
those is a source, asked in order and merged field by field.

## The section

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { media } from '@webx-ui/module-media'
import { seo } from '@webx-ui/module-seo'
import '@webx-ui/module-seo/style.css'

createAdmin({
  modules: [media(), seo(), settings()],
})
```

It appears under **System** in the navigation, above the settings, once `webx-ui/module-seo` is
installed and migrated on the server. Permissions: `seo.view` to look, `seo.manage` to change.

The share image is picked with `wx-media`, which the card looks up in the panel’s own registry —
so installing the library is all it takes. Nothing is imported from it here: this package does
not depend on the library, and a panel without one still edits every other SEO field and simply
has no picture. A caller that wants a different field than the registered one passes
`seo({ mediaField })`.

## Where a value comes from

Sources are asked highest first and merged **field by field**. A rule that fills in nothing but a
title keeps the description and the picture that came from below it — merging whole objects
instead is how one rule wipes out half a page's markup and the engine looks broken.

| Priority | Source           | Reads                                                   |
| -------- | ---------------- | ------------------------------------------------------- |
| 100      | `UrlRuleSource`  | `seo_urls` — the rules written for addresses            |
| 50       | `EntitySource`   | `seo_meta` — the entity's SEO card                      |
| 30       | `FallbackSource` | the entity's own name, lead and picture (`seoFallback`) |
| 10       | `DefaultsSource` | `settings('seo.*')`                                     |

A project adds its own from a provider:

```php
use WebxUi\Seo\Rendering\{SeoData, SeoSource, SeoSources};

final class CampaignSource implements SeoSource
{
    public function priority(): int { return 70; }

    public function forUrl(string $url, ?object $subject = null, ?string $locale = null): ?SeoData
    {
        return str_starts_with($url, '/promo/')
            ? SeoData::make(['robots' => 'noindex'])
            : null;
    }
}

app(SeoSources::class)->register(new CampaignSource);
```

Answer with the fields you know and leave the rest null. Whatever stands below fills those in.

## When nobody wrote a card

An entity says what its page is called without anybody writing it a card, by implementing
`HasSeoFallback`:

```php
use WebxUi\Seo\Contracts\HasSeoFallback;
use WebxUi\Seo\Rendering\SeoData;

final class Recipe extends Model implements HasSeoFallback
{
    public function seoFallback(?string $locale = null): ?SeoData
    {
        return SeoData::fallback(
            $this->getTranslation('title', $locale),
            $this->getTranslation('lead', $locale),   // markup stripped, cut near 300 characters
            $this->coverUrl(),                        // a /path is made absolute
        );
    }
}
```

`FallbackSource` asks it below the card and above the defaults. So the card wins every field it
fills in, the entity's own picture wins over `seo.default-og` — which is only "shown when a page
has no picture of its own" — and the title goes through the title template like any other: the
site's name is added once, by the template, and not typed into a view.

A page that is a route rather than a record — the index of recipes, the blog feed — has no
entity to ask. The view names it:

```blade
@webxSeo(fallback: ['title' => trans('webx-recipes::site.title')])
```

The modules' views used to print `<title>` by hand when `$meta->title === null`. That missed the
template, `og:title` and every picture; a site's copy of a module view can drop the block.

## Printing the head

```blade
<head>
    @webxSeo
</head>
```

With an entity to name:

```blade
@webxSeo($page)
{{-- or, when a tag reads better --}}
<x-webx-seo::head :for="$page" />
```

Both print `<title>`, the description, keywords and robots meta tags, the canonical link, the
Open Graph properties and every JSON-LD block. Around that, for the page being served:

- **A canonical on every page.** Nobody wrote one — the page names itself, with the query cut to
  what makes a different page (`?page=2` stays, `?utm_source=` goes). A written one always wins.
- **`hreflang`** — the same page in the site's other languages, and `x-default` on the default
  one. Only where the language is in the path (`strategy: prefix`): with `header` every language
  shares one address and there is nothing to point at.
- **The trail** as a `BreadcrumbList`, when the entity has one, and the entity's own schema.org
  blocks — see [Contracts for module authors](#contracts-for-module-authors).
- **The social card** — the whole Open Graph set, `article:*` and `twitter:*`, from what the page
  already says; see [Social cards](#social-cards).

Without `:for` the head takes the entity the address registry found for the request. Which of
these are printed at all is `webx-seo.print` in `config/webx-seo.php` (`hreflang`, `breadcrumbs`,
`structured_data`, `og`, `article`, `twitter` beside the old ones), so a site that writes its own
canonical links turns that one off rather than working around it.

Everything goes through Blade's escaping, and JSON-LD through `json_encode` with `JSON_HEX_TAG`:
a title with a quote in it cannot end an attribute, and a `</script>` inside a string cannot close
the block.

## Writing a rule

A rule covers one address or a shape of them. What is compared is the path **with its query
string**, as it arrived — `/catalog/shoes?page=2` — with a trailing slash removed from everything
but the root. The query is part of it because pages of filters and pagination are half of what
rules get written for.

| Kind    | Pattern        | Matches                                                  |
| ------- | -------------- | -------------------------------------------------------- |
| `exact` | `/about`       | that address and no other                                |
| `mask`  | `/catalog/*`   | one segment: `/catalog/shoes`, not `/catalog/shoes/red`  |
| `mask`  | `/catalog/**`  | any number of them                                       |
| `regex` | `#^/p/(\d+)$#` | whatever it says — stored as written, delimiters and all |

Rules are tried exact first, then mask, then regex; inside a group by `priority` descending, and
by age when two are equal. The first match wins, and the table lists them in that same order —
reading it top to bottom is reading what will happen.

A regular expression is checked when it is saved, so a typo is a message under the field rather
than a 500 on every page of the public side. One that got in anyway — written straight into the
database — never matches instead of throwing.

The active rules are kept as one compiled list in the cache and thrown away whenever any of them
is saved or deleted, so switching a rule off stops it on the next request.

## Redirects

The same matcher, and the same three kinds. A mask or a regular expression may put back what it
caught: `/catalog/*` → `/shop/$1`.

Redirects are **global middleware**, not a member of the `web` group, and that is not an
implementation detail: the addresses worth redirecting are the ones the site no longer has a route
for, and a request for one of those never reaches a middleware group at all — the router throws
first. The panel's own addresses are stepped over, so a mask an editor writes cannot lock them out
of the screen they wrote it on.

A redirect that would send an address back to itself is skipped rather than refused when it is
saved: a mask is a loop only for some of the addresses it covers, and the rest are still worth
serving. The row says so in the table. Chains are not collapsed — `A → B → C` costs the browser
two requests, which is cheaper than walking a graph an editor can make circular from two screens.

## The redirects nobody wrote

The third tab, **Automatic**, is the other half of the same story and belongs to
[`webx-ui/routing`](./routing): renaming a page or moving a branch leaves the old address behind as
an alias that answers 301, which is what keeps a bookmark, an inbound link and a search result
alive through an edit in the panel. A reader who lands on a dead address does not care which half
of the system answered, so an editor chasing one should not have to either.

Read only, and not for want of a form. An alias belongs to the entity that moved: the entity makes
it, a second rename repoints it, and deleting the entity takes it away. A panel that could edit one
would be a panel that can make the registry disagree with the site. To give an old address a
different answer, write a rule on the **Redirects** tab — rules are tried before routing, so yours
wins and the alias underneath stops mattering.

That is also why the form warns when the address it is about to take over is a live page. It
warns and saves anyway: shadowing a page is a legitimate thing to want, and refusing it here would
make the common case — an address that should now go somewhere else — impossible to express.

## `robots` and `robots.txt` are different things

- `robots` on a rule is that page's own meta directives — `noindex, nofollow` — printed into
  `<meta name="robots">`. The card offers the five that get used as checkboxes; anything else
  already stored is kept as written and shown under them.
- `seo.robots-txt` is one setting for the whole site, served at `/robots.txt`.

If a real `public/robots.txt` exists, the web server hands it over before Laravel is asked and the
setting never gets a say. That is the server doing its job, not a bug in the module. With the
setting empty the route answers 404 for the same reason.

## The settings tab

The module lays a patch over `settings.index`. These ids are a contract — a project writes its own
patch against them:

| id               | type          | name                 | what it is                      |
| ---------------- | ------------- | -------------------- | ------------------------------- |
| `seo`            | `wx-tab`      |                      | the tab                         |
| `seo-card`       | `wx-card`     |                      |                                 |
| `default-og`     | `wx-media`    | `seo.default-og`     | the fallback share image        |
| `title-template` | `wx-input`    | `seo.title-template` | `{title} — {site}`              |
| `home-crumb`     | `wx-input`    | `seo.home-crumb`     | the first crumb, per language   |
| `robots-txt`     | `wx-textarea` | `seo.robots-txt`     | what `/robots.txt` answers      |
| `org-name`       | `wx-input`    | `seo.org-name`       | Organization, per language      |
| `org-logo`       | `wx-media`    | `seo.org-logo`       | Organization                    |
| `org-socials`    | `wx-repeater` | `seo.org-socials`    | profiles elsewhere, as `sameAs` |

The dot in `seo.default-og` is part of the name, not a path. A key with a dot in it is one key,
and both the server's validator and its tests have to be told so.

The title template is applied to whatever title the sources agreed on — a rule's, a card's, an
entity's own name. A placeholder with nothing behind it takes its separator with it, so a site with
no name does not publish "Contacts —".

A title that already names the site is printed as written: an editor who typed "About us | Acme"
into a card meant exactly that, and "About us | Acme — Acme" is what the template would make of
it. The site's name is compared on letters and digits alone, ignoring case, so "Acme Studio" in a
title is the project called "AcmeStudio". The home page, when nobody wrote it a title, is called
by the site's name rather than "Home — Acme".

## Social cards

Nobody types a social card. Every line is worked out from what the page already has, so an editor
who wrote a title and picked a cover has written the card too:

| Line                                                             | Where it comes from                                                                            |
| ---------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `og:title`, `og:description`                                     | the title after the template, the description — the same merge as `<title>`                    |
| `og:url`                                                         | the canonical                                                                                  |
| `og:type`                                                        | the entity's (`HasOpenGraph`); `website` for everything else                                   |
| `og:site_name`                                                   | `general.project-name`                                                                         |
| `og:locale`, `og:locale:alternate`                               | the page's language, and the languages `hreflang` lists for it                                 |
| `og:image`, `:type`, `:width`, `:height`, `:alt`                 | the first picture of the sources a network can show — card, the entity's own, `seo.default-og` |
| `article:published_time`, `:modified_time`, `:section`, `:tag`   | an article's dates, its main rubric or category, its tags                                      |
| `twitter:card`, `:title`, `:description`, `:image`, `:image:alt` | the same values as Open Graph                                                                  |
| `twitter:site`                                                   | the x.com / twitter.com profile among `seo.org-socials`                                        |

A line with nothing to say is not printed, and each group has its switch: `print.og`,
`print.article`, `print.twitter`.

**The picture.** An SVG is never `og:image` — no network shows one — so a press outlet whose logo
is a vector falls through to the next source's picture, usually the site's default. With
`module-media` installed the library's own record gives the type and the size, and a landscape
photo at least 1200×630 is shared as a variant cut to exactly that (`webx-seo.og.image`; `null`
shares the picture as it is). A portrait photo or a wide logo is shared whole: cut to 1.91:1 it
would lose what it is a picture of. `og:image:alt` is the alt the picture was given where it was
picked, and the page's title where it was not. `og:image:secure_url` is not printed: every address
is already the https one, and the line would repeat it.

**The kind of page.** `og:type` is `article` for a blog article and for a recipe — Open Graph has
no recipe type, and `article` is what carries a date and a section into the card. Everything else
is `website`: the home page, listings, ordinary pages, services, events, press outlets, vacancies,
the catalogue. Open Graph has no current type for an event or a service, and a wrong type is worse
than the general one. A module that has an article says so with `HasOpenGraph` (below).

**The language.** `og:locale` is `language_TERRITORY`: `pt-BR` stays `pt_BR`, a bare code takes its
usual country (`en` → `en_US`, `uk` → `uk_UA`, `de` → `de_DE`). A site that means another one says
so: `'og' => ['locales' => ['en' => 'en_GB']]`.

**Twitter.** X reads `og:*` where `twitter:*` is missing, so the mirrored lines are there for the
readers that do not fall back. A site that wants a shorter head turns `print.twitter` off; it
loses `twitter:site` too, the one line Open Graph cannot say.

`seo_test_url` and the panel's «Test an address» answer with `social` — these lines exactly as the
head prints them.

## `wx-seo`, the card

The module registers `wx-seo` as a field type, on both halves. Its value is everything a page says
about itself, as one object:

```json
{
  "title": { "en": "Shoes", "uk": "Взуття" },
  "h1": {},
  "description": { "en": "Everything we sell" },
  "keywords": {},
  "og_title": {},
  "og_description": {},
  "og_image": { "path": "catalog/cover.jpg" },
  "canonical": null,
  "robots": "noindex, nofollow",
  "json_ld": null
}
```

The text fields are language maps and grow the same chip every localized field in the panel does.

The share fields — `og_title`, `og_description`, `og_image` — are not in the card by default: the
card is filled in from the page ([Social cards](#social-cards)), and fields nobody needs to fill in
look forgotten. A site that wants to override them by hand turns them back on with
`WEBX_SEO_OG_FIELDS=true` (`webx-seo.og.panel_fields`); the card's `share-fields` prop decides for
one screen. Values already stored are honoured either way, above everything derived.

`og_image` is not a language map: there are no per-language pictures anywhere in the panel yet, and a column
that already holds an object would read `path` as a language code the day one was added.

An entity's form gets the whole card from a patch, laid over the screen the content module
described:

```json
{
  "op": "replace",
  "target": "seo-placeholder",
  "node": { "id": "seo-fields", "type": "wx-seo", "name": "seo" }
}
```

The counters beside the title and the description are **soft**. Long is not wrong — search engines
shorten what they shorten — so nothing refuses a longer line; `webx-seo.trim` makes the server cut
to them, and it is off by default because cutting an editor's title behind their back reads as a
bug.

## An entity that speaks for itself

A content module gives its model one trait and it has somewhere to keep that value:

```php
use WebxUi\Seo\HasSeo;

class Page extends Model
{
    use HasSeo;
}
```

The fields live in `seo_meta`, one row per entity, translated the way everything else in the
panel is. The trait reads and writes them:

```php
$page->seoValue();       // the card's value: every language of every field
$page->saveSeo($value);  // write it back — an empty value removes the row
$page->seoData('en');    // what the page contributes to its own <head>, or null
```

An entity nobody has written anything for has **no row**, and `EntitySource` then contributes
nothing, which is what lets the site's defaults through. That is the whole reason an emptied card
deletes the row instead of keeping one full of blanks: "nothing written here" and "everything
written here is blank" look the same to an editor and mean opposite things to the merge.

Where the value goes once the form has checked it is the host screen's business. The page editor
keeps it out of the draft on purpose — a description that only reaches search engines at the next
publication is the kind of thing an editor finds out about from a search engine.

## Structured data

Three levels, on purpose:

- **About the site** — `Organization`, `WebSite` — comes from the settings fields above, and the
  JSON-LD is assembled from them.
- **About an entity** — `BlogPosting`, `Product`, `BreadcrumbList` — is generated from the entity
  by whoever owns it, through `HasStructuredData` and `HasBreadcrumbs`. Typed in by hand it drifts
  away from the page within a month and starts lying to search engines.
- **One-off exceptions** — the JSON-LD field on a rule or on the card.

There is no schema.org builder in the panel and there will not be one: the vocabulary has hundreds
of types and filling them in by hand is how markup starts lying.

## The sitemap

`/sitemap.xml` is an index with a file per type of the address registry — `/sitemap-page.xml`,
`/sitemap-article.xml` — and `/sitemap-routes.xml` for named routes a module asked for. A type
past 45 000 addresses is cut into `sitemap-{type}-1.xml`, `-2`, and so on.

It has **no rules of its own**. An address is in it when the registry has a canonical row for it,
the entity says it is on the site (`Visible`), and the `<head>` of that page would print neither
`noindex` nor a canonical pointing elsewhere — asked of the same resolver that prints the head. So
a rule with `noindex` on `/catalog/**` takes every one of those out of the map too, and nobody has
to tell the map. On a multilingual site each line carries the same `hreflang` set as the page.

- Built on the first request and kept until something it depends on is saved: a registry row, a
  card, a rule, a visible entity, an `seo.*` setting. The TTL (a day) is for what changes without
  a save — an article dated for tomorrow.
- `php artisan webx:seo:sitemap` builds it ahead of the first crawler: put it in the deploy.
- `/robots.txt` gets a `Sitemap:` line by itself if the setting does not have one.
- `WEBX_SEO_SITEMAP=false` turns the whole thing off for a site with a map of its own.
- The files are laid out like
  [Google's multilingual example](https://developers.google.com/search/docs/specialty/international/localized-versions#sitemap):
  an element a line, the attributes of each `xhtml:link` in a column. Each file names
  `/sitemap.xsl`, so a browser shows that same text with every address a link — not a table,
  because whoever opens a map is checking the markup a crawler reads. Crawlers ignore both.
  `WEBX_SEO_SITEMAP_XSL=false` leaves the stylesheet out.

### A type that is not a page

A site may bind its own handler over a module's so that a type never shows a page: events that go
straight to an external booking page, categories that are only a filter on the list. Those
addresses answer 301, and a redirect does not belong in a sitemap. The handler says so by
implementing `NotAPage` from `webx-ui/routing`:

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

The type then has no file and is left out of the index. The card and `seo_sitemap_status` list it
under `excluded_types` with the reason `not-a-page`, the class that said so and how many addresses
it covers; `test-url` answers `not-a-page` for each of them. Nobody has to mark every new event
`noindex`. The built map is kept under the set of such types, so a deploy that adds or removes the
binding is picked up on the next request without a rebuild.

There is deliberately no config list of types to leave out. The binding is what makes the addresses
redirect, and a second list would have to be kept in step with it. A type that does show pages but
should stay out of the index is closed with a `noindex` rule (`/events/**`), and then the head and
the map agree.

A route that is not an entity — a feed, an index page — is added from the module's provider:

```php
app(SitemapRoutes::class)->register('blog.feed');
```

In the panel, the card **Sitemap** above the rules shows the address, the number of addresses in
each file, when it was built, and how many visible pages were **left out** and why — `noindex` or
another canonical. That last line is the first thing to read when a page is missing from a search
engine. Types that are not pages are named on a line of their own, with their number of addresses.

## Contracts for module authors

A content module gets all of the above by implementing interfaces on its model; nothing is
registered. Each one asks one question.

| Contract                                 | Package   | The question                                  |
| ---------------------------------------- | --------- | --------------------------------------------- |
| `WebxUi\Routing\Contracts\Visible`       | `routing` | is it on the site — for the handler and a map |
| `WebxUi\Seo\Contracts\HasBreadcrumbs`    | `seo`     | where does it stand                           |
| `WebxUi\Seo\Contracts\HasStructuredData` | `seo`     | what is it, in schema.org                     |
| `WebxUi\Seo\Contracts\HasOpenGraph`      | `seo`     | what kind of page, to a social network        |

```php
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Seo\Contracts\HasStructuredData;
use WebxUi\Seo\HasSeo;

class Service extends Model implements HasBreadcrumbs, HasStructuredData, Visible
{
    use HasSeo, HasTranslations, HasUrl;

    // The handler answers 404 by this, and the sitemap leaves the row out by the same answer.
    public function isVisible(?string $locale = null): bool
    {
        return $this->published_at !== null && ! $this->trashed();
    }

    public function scopeVisible(Builder $query, ?string $locale = null): Builder
    {
        return $query->whereNotNull($this->qualifyColumn('published_at'));
    }

    public function visibleUpdatedAt(): ?CarbonInterface
    {
        return $this->published_at;
    }

    // The home is added in front by module-seo; the entity is the last step.
    public function breadcrumbs(string $locale): array
    {
        return [
            new Crumb($this->category->getTranslation('title', $locale), $this->category->url($locale)),
            new Crumb($this->getTranslation('title', $locale), $this->url($locale)),
        ];
    }

    public function structuredData(string $locale): array
    {
        return [[
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $this->getTranslation('title', $locale),
            'url' => $this->url($locale),
        ]];
    }
}
```

**One trail, printed twice.** The `BreadcrumbList` in the head and the crumbs a reader sees come
from the same list, so the view prints them with the component rather than working them out again:

```blade
<x-webx-seo::breadcrumbs :for="$service" />
```

It renders a `<nav aria-label>` with a list, the last step as text with `aria-current="page"`, and
nothing at all when the entity has no trail (the home page). Publish `webx-seo-views` to restyle
it. The first step is the site's home, named by the `seo.home-crumb` setting in the language of the
page — "Home" from the dictionary until somebody writes one. A step with a null address is printed
as text and keeps its place in the list.

Whether the package views of a module print the crumbs at all is that module's switch:
`webx-pages.breadcrumbs`, `webx-blog.breadcrumbs`, `webx-services.breadcrumbs` (or
`WEBX_PAGES_BREADCRUMBS=false` and the like), on by default. It is per module because the usual
answer is "in the blog and nowhere else". It only hides the visible trail — the `BreadcrumbList` in
the head stays, and is switched by `webx-seo.print.breadcrumbs`. A site that keeps its own view
decides in its markup and never reads the switch.

**What depends on the response, not the entity** — the articles on page two of a category — is the
handler's to say. Push it before rendering the view; it lives until the end of the request:

```php
app(Seo::class)->push([
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'itemListElement' => $items->values()->map(fn ($item, $i) => [
        '@type' => 'ListItem',
        'position' => $items->firstItem() + $i,
        'url' => $item->url(),
    ])->all(),
]);
```

What ships: `Page` (ancestors in the tree), `Article` (feed → main rubric → article, and a
`BlogPosting`), `Rubric` and `Tag` (feed → it), and the `ItemList` on a rubric page.

## Why is this page saying that?

The most common question the section gets, and it takes one call to answer. **Check an address**,
at the top of the section, reports the redirect that catches it, the rule that matched, what each
source contributed, what the page ends up with — and whether it is in the sitemap, and if not,
why: not published, `noindex`, another canonical, an old address, or not a page of the site at
all.

```ts
import { createSeoApi } from '@webx-ui/module-seo'

const api = createSeoApi(useAdmin())
const answer = await api.test('/catalog/shoes?page=2')
```

Or, from an agent: `seo_test_url`, beside `seo_urls_list`, `seo_urls_get`, `seo_urls_set`,
`seo_urls_delete`, `seo_redirects_list`, `seo_redirects_set`, `seo_redirects_delete` and
`seo_import_redirects` — which turns the list of old
and new addresses that comes out of every site migration into rows in one call — and
`seo_sitemap_status`, the numbers of the card. There is no tool to rebuild the map: it rebuilds
itself.

## Talking to it directly

| Method and address                    | What it does                                                |
| ------------------------------------- | ----------------------------------------------------------- |
| `GET /api/cms/seo/urls`               | the rules, in the order the site tries them                 |
| `POST /api/cms/seo/urls`              | add one                                                     |
| `GET`, `PUT`, `DELETE` on `urls/{id}` | one rule; `PUT` replaces the whole of it                    |
| `GET /api/cms/seo/redirects` …        | the same for redirects                                      |
| `GET /api/cms/seo/aliases`            | the addresses renames left behind; read only                |
| `POST /api/cms/seo/test-url`          | what an address ends up saying, and where each part is from |
| `GET /api/cms/seo/sitemap`            | the sitemap: files, counts, when built, what was left out   |
| `POST /api/cms/seo/sitemap`           | build it again now (`seo.manage`)                           |

Lists arrive as Laravel's own paginator, which the table in the core reads as it comes.
