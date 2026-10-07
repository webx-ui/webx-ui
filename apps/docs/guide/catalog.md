# Catalogue

A shop's catalogue is not one module but a core and its satellites. `webx-ui/module-catalog` is the
core: products, a tree of categories, a price, a gallery, the engine behind lists and filters, the
storefront and the import and export of files. Everything else a shop may or may not want —
properties, stock, brands, labels, landing pages, a search server — is a satellite that plugs into
the core's registries. A showcase without a cart gets by with the core and two satellites; a parts
shop of a hundred thousand products takes them all. It is one installation either way, assembled
from a different set of packages, and no package knows which others stand beside it.

The core leans on the rest of the panel the way every module does: the addresses are the registry
of [`webx-ui/routing`](/guide/routing), the tree is `webx-ui/nested-set`, the screens and the
journal are `module-admin`, what a page says about itself is [`module-seo`](/guide/seo), the
previews are [`module-media`](/guide/media), the languages are `webx-ui/localization`.

| Package                     | What it adds                                                         |
| --------------------------- | -------------------------------------------------------------------- |
| `module-catalog`            | products, categories, price, gallery, filter, storefront, CSV/XLSX   |
| `module-catalog-properties` | typed properties, their values and sets per category — most filters  |
| `module-catalog-stock`      | stock statuses and whether a product can be bought                   |
| `module-catalog-brands`     | brands, one per product, each with a page of its own                 |
| `module-catalog-labels`     | labels — top, sale, new — as badges and a filter                     |
| `module-catalog-landings`   | landing pages: a filter set under an address, a text and an SEO card |
| `module-catalog-manticore`  | Manticore Search as the engine, for catalogues past a few thousand   |

Each is a pair, a Composer package and an npm package of the same name, like every section of
the panel. The cart, orders, customers, payment and delivery are outside the family; the one
question they will ask the catalogue — can this be bought, and if not, why — is already a
registry of the core.

The design of the family is `docs/architecture/WEBX_UI_CATALOG.md`; the core's spec is
`WEBX_UI_MODULE_CATALOG.md`.

## Install

The core first, then whichever satellites the shop needs:

```bash
pnpm add @webx-ui/module-catalog @webx-ui/module-catalog-properties \
  @webx-ui/module-catalog-stock @webx-ui/module-catalog-brands \
  @webx-ui/module-catalog-labels @webx-ui/module-catalog-landings
composer require webx-ui/module-catalog webx-ui/module-catalog-properties \
  webx-ui/module-catalog-stock webx-ui/module-catalog-brands \
  webx-ui/module-catalog-labels webx-ui/module-catalog-landings
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import { catalogProperties } from '@webx-ui/module-catalog-properties'
import { catalogStock } from '@webx-ui/module-catalog-stock'
import { catalogBrands } from '@webx-ui/module-catalog-brands'
import { catalogLabels } from '@webx-ui/module-catalog-labels'
import { catalogLandings } from '@webx-ui/module-catalog-landings'
import { media } from '@webx-ui/module-media'
import '@webx-ui/module-catalog/style.css'
import '@webx-ui/module-catalog-properties/style.css'
import '@webx-ui/module-catalog-stock/style.css'
import '@webx-ui/module-catalog-brands/style.css'
import '@webx-ui/module-catalog-labels/style.css'
import '@webx-ui/module-catalog-landings/style.css'

createAdmin({
  modules: [
    ...catalog(),
    ...catalogProperties(),
    ...catalogStock(),
    ...catalogBrands(),
    ...catalogLabels(),
    ...catalogLandings(),
    media(),
  ],
})
```

`php artisan webx:panel --sync` writes the imports and the npm dependencies from what Composer
installed, so a site rarely types the block above by hand.

`catalog()` returns two sections, **Products** and **Categories**, because the navigation is one
entry per module and the tree is opened often enough to want its own. «Deleted» and «Exchange» are
in the `···` of the products. The satellites stand beside them in the «Catalog» group — stock and
labels under a «Dictionaries» caption. A section whose server half is not installed never appears.
Keep the default path `/catalog`: the server's refusal of a taken article number links to
`{panel}/catalog/products/{id}`.

**Permissions** are the core's three, and the satellites have none of their own:
`catalog.view` opens the sections and lists, `catalog.manage` writes everything except deleting,
`catalog.delete` deletes, restores and opens «Deleted». A person who may edit a product may set its
brand, labels and properties — separate rights would be places to forget.

`php artisan webx:demo` fills the catalogue with a tree of about fifteen categories and a hundred
and fifty products, some unpublished and deleted, so that the filters have something to count.

## Products and categories

### Addresses

Both live at the root of the site, in the same flat space as the pages:

| What     | Address                 | Notes                                                    |
| -------- | ----------------------- | -------------------------------------------------------- |
| category | `/laptops`              | flat at any depth; type `catalog.category`               |
| product  | `/macbook-air-13-12345` | `{slug}-{id}`; type `catalog.product`                    |
| root     | `/catalog`              | off by default: `WEBX_CATALOG_ROOT=true`                 |
| search   | `/catalog/search?q=`    | always there, always `noindex`; the prefix is the root's |

**A category's address does not contain its parents.** `/gaming-laptops`, not
`/laptops/gaming`: moving a category in the tree changes no address, and the breadcrumbs are built
from the tree. The price is that a category's slug is unique across the whole site in its
language — a slug taken by a page or another category is a `422` naming whoever holds it, and `_`
is refused, because it marks a filter.

**A product's slug need not be unique,** because the id makes the address so. Any other spelling —
an old slug, a typo in it — answers `301` to the current one. The suffix is the id rather than the
article number: an article number may be missing, changes on import, and sometimes holds a slash.

The root `/catalog` is a page of the top categories with a filter over everything. Most shops do
not want it — a top category is just a list — so it is off, and off means no address at all.
`WEBX_CATALOG_ROOT_PREFIX` moves it (and the search with it). The root is a route and answers
before any page with the same address; `php artisan webx:doctor` names such a page.

### The states of a product

| State       | Lists, search, filters | Its address                                                               |
| ----------- | ---------------------- | ------------------------------------------------------------------------- |
| published   | yes                    | the full page                                                             |
| unpublished | no                     | a trimmed page, `200`, `noindex`                                          |
| deleted     | no                     | `301` to its main category, or to a visible additional one; `410` if none |

Published is not quite enough to be seen: at least one of the product's categories has to be
published together with all its ancestors. A published product with no visible category behaves as
an unpublished one. The **trimmed page** (`product-unavailable`) has the name, a picture, the
article number and «no longer sold» — a link that came from somewhere still lands on something.

A deleted product goes to «Deleted» and keeps its pictures; restoring it brings its address back.
There is no permanent delete in the panel, because links from orders must stay alive.

### Categories

The tree is dragged into order in the panel, with the number of products in each branch — main
and additional categories, descendants included. A product has one **main** category, which its
breadcrumbs follow, and any number of additional ones.

An **unpublished** category answers `404` and hides its subtree; a product that has another
visible category stays visible through it. A category can be **deleted** only when it holds no
live products and no subcategories — otherwise the panel says how many — and its address then
answers `410`.

The «Filters» tab of a category chooses which filters it shows and in what order. It is off by
default (`WEBX_CATALOG_CATEGORY_FACETS`): with the category and the price alone there is nothing to
arrange. Once properties or other satellites bring filters, switch it on. A category without its
own setting inherits its nearest ancestor's, and a filter registered after a category was set up
stays hidden there until somebody turns it on — a new module should not quietly change somebody
else's filters.

### Price and the optional fields

```php
// config/webx-catalog.php
'price' => [
    'enabled' => (bool) env('WEBX_CATALOG_PRICE', true),
    'currency' => env('WEBX_CATALOG_CURRENCY'),   // ISO 4217, one for the site
],
'fields' => [
    'barcode' => (bool) env('WEBX_CATALOG_BARCODE', true),
    'facets' => (bool) env('WEBX_CATALOG_CATEGORY_FACETS', false),
    'video' => (bool) env('WEBX_CATALOG_VIDEO', true),
],
'units' => ['pcs', 'kg', 'g', 'm', 'm2', 'm3', 'l', 'pack', 'set'],
'default_unit' => 'pcs',
```

A product has a price and an old price. A catalogue without prices exists — a showcase, a
manufacturer's range — and `WEBX_CATALOG_PRICE=false` takes the price out of the form, the filter,
the index and the exchange at once; the columns stay in the table. The **currency** is read only by
the markup: until it is set, a product's `Offer` is left out of its structured data. A unit is a
key, and its word is `webx-catalog::units.<key>` in the module's dictionary, so a site adds a unit
in the config and its word in `lang/vendor/webx-catalog`.

A product also has an `external_id` — the key of an accounting system, for whoever writes the
integration — and a `priority`, the hand-set weight of the default sort.

### Sorting and popularity

The reader is offered `webx-catalog.sorts` in that order. The default sort is the steps of
`default_sort`: the hand-set priority, then popularity, then the newest. Popularity is the
product's views, counted in the cache and flushed every five minutes, decayed nightly by
`popularity.decay`; a project that counts it differently binds its own `PopularityFormula`.
`?sort=` is the one query parameter of the catalogue, and a page with it is `noindex` with a
canonical to the address without.

## Facets and filter addresses

A **facet** is something a list can be narrowed by. The core registers two: `category` (a tree) and
`price` (a range, when prices are on). Each satellite registers its own — `brand`, `label`,
`stock`, and one per filterable property. There are four kinds: `Terms` (a list of values),
`Range` (from–to), `Toggle` (yes/no) and `Tree` (values with parents, where choosing a parent means
any of its descendants). The engine does the counting, the same way for every facet: the counts of
a facet are computed with every filter except its own, so choosing Apple still shows the other
brands.

### The address of a filter

Filters live in the path, never in query parameters. A segment holding `_` is a filter; one
without is a category:

```
/laptops                                the category
/laptops/brand_apple                    one value of one facet
/laptops/brand_apple_dell               two values of one facet
/laptops/brand_apple/color_black        two facets
/laptops/price_100-500                  a range
```

The `_` is the boundary because no slug can hold it — not a category's, not a facet code, not a
value's — so an address cannot be read two ways. **There is one spelling:** segments in the order
of the facets, values by slug, and anything else answers `301` to that. In a tree, a chosen parent
swallows its chosen children. On a category page, choosing one subcategory is not a filter but a
move: `/laptops/category_gaming-laptops` is written `/gaming-laptops`.

A facet's code in the address is its key unless the site translates it, per language:

```php
'facet_codes' => ['price' => ['de' => 'preis'], 'brand' => ['de' => 'marke']],
```

A renamed code or value slug keeps the old address alive as a `301`.

### What is indexed and what is closed

- **Open:** the category itself; exactly one value of exactly one facet, when the facet is
  _indexable_ and the list is not empty; landing pages.
- **Closed** (`noindex, follow`): everything else — two values, two facets, any range, any toggle,
  an empty first level, `?sort=`. The filter's links to closed addresses carry `rel="nofollow"`,
  and the sitemap does not list them.

Reference books are indexable by default and ranges and toggles are not — «in stock» does not
deserve a page of its own. Of the satellites' facets, `brand` is indexable and `label` and `stock`
are not; a property says so with its own flag. The open first level gets its SEO from a pattern,
«{category} {value}», through `module-seo`; a rule written for its address beats it, as everywhere.
The sitemap lists the categories, the non-empty first levels (the `catalog-filters` file) and the
landings.

The filter itself is links, not a form: it works without JavaScript, values with no products are
grey and unlinked, and a long list folds into `<details>`. The price is a small form that answers
`302` to the address with the segment; a slider is the site's to add.

## The storefront

### Templates

Every page of the catalogue is a Blade view of the package, overridden the usual way — by a file of
the same name in `resources/views/vendor/webx-catalog/`:

| View                  | What it is                                      |
| --------------------- | ----------------------------------------------- |
| `category`            | a category's list, and every list built like it |
| `root`, `search`      | the root of the catalogue and the search        |
| `product`             | a product's page                                |
| `product-unavailable` | the trimmed page of an unpublished product      |
| `filter`, `filter/*`  | the filter, and a partial per kind of facet     |
| `grid`, `card`        | the grid of products and one card in it         |
| `sort`, `pagination`  | the sort links and the pages                    |
| `breadcrumbs`         | the path down the tree                          |

```bash
php artisan vendor:publish --tag=webx-catalog-views
```

Publishing copies all of them; keep the ones you change and delete the rest, and those go on
coming from the package, fresh with every release.

The views stand in a layout: `WEBX_CATALOG_LAYOUT=layout` makes them `<x-layout>`, the component a
site keeps in `resources/views/components/layout.blade.php`, with a `head` slot and the default
one. Empty, they print the package's own bare document. `php artisan webx:panel --sync` writes the
setting when the component exists. The deal is the same for every module — see
[Pages](./pages.md#the-layout).

### Points for the satellites

A card and a product page have named places the satellites print into, so that a site overriding
`card.blade.php` does not lose the brand line or the badges:

| Point                         | Where                 | Who prints there by default   |
| ----------------------------- | --------------------- | ----------------------------- |
| `catalog.card.badges`         | over a card's picture | labels                        |
| `catalog.card.meta`           | under a card's name   | brand, stock, properties      |
| `catalog.product.aside`       | beside the buy button | brand, stock, landings        |
| `catalog.product.tabs`        | below the product     | properties («Specifications») |
| `catalog.product.unavailable` | on the trimmed page   | —                             |
| `catalog.listing.top`         | over a list's grid    | landings («Collections»)      |
| `catalog.listing.bottom`      | under a list's pages  | landings (neighbours)         |

A view prints a point with `@webxPart` and a fallback partial, which prints every part registered
there, in order, each having loaded what it needs for the whole page in one query:

```blade
@webxPart('catalog.card.meta', ['product' => $product], 'webx-catalog::points.card-meta')
```

Each satellite's own views are published with its own tag
(`--tag=webx-catalog-brands-views`, `-labels-`, `-stock-`, `-properties-`, `-landings-`).

### Products in any template

`products()` is a query for the cards of a page that is not the catalogue — a shelf on the home
page, «new in this category» in an article:

```blade
@foreach (products()->category('laptops')->sort('popular')->take(8) as $card)
    <a href="{{ $card['url'] }}">{{ $card['name'] }}</a>
@endforeach
```

The satellites add their own narrowing to it: `->brand('apple')`, `->label('sale', 'new')`,
`->inStock()`, `->property('color', 'black')`. A collection block can show the same as «products»
through `wx-collection`, and the menu can link to categories and products.

### Can it be bought

The template draws «Buy» or «Ask the price» from one answer, `Purchasability`: a chain of rules,
each of which may refuse with a code and a sentence. The core's are `unavailable` (the product is
not visible) first and `price-on-request` (prices are on and this one is empty) last; stock puts
`stock` in between. The cart, when there is one, asks the same question.

### SEO

A category, a product and the root have the `module-seo` card. The structured data is `Product`
with `Offer` (when the price is on, filled in and the currency is set), `BreadcrumbList`, and
`ItemList` on a category; each video is a `VideoObject`. A page of a list answers its SEO through
the catalogue: the category's own card on the plain page, the pattern on an open first level,
`noindex, follow` on everything closed. An address rule of `module-seo` beats all of it.

Nobody types a `<title>` into a view. With an empty SEO card a product names its page itself: the
name through the site's title template, the summary (or the description, cut short) as the
description, the main picture as `og:image`, above the site's default social image. A page of a list
is called by its heading — the category's name, a brand's, «Catalogue» on the root — and on the
plain page the owner's description and cover (a brand's logo) come with it; once a filter is chosen
they are not that page's, the same as the card. A copy of a view published before this still has an
`@if ($meta->title === null)` block — delete it; keep `$meta` only where the `<h1>` reads
`$meta->h1`.

## The gallery

A product's pictures are files on a disk of the catalogue's own (`webx-catalog.images.disk`,
`public` by default), under `catalog/{id div 1000}/{id}/`, not rows of the media library — half a
million product photos belong to no editor's tree. The first picture by position is the main one.
Each has an `alt` and a `title` in every language. The editor adds pictures from a file or an
address, orders them, captions them and takes them away; each of these is a request of its own,
not part of the form's save, and each lands in the product's journal.

### Video

A video is **attached to a picture**, which becomes its poster: lists, feeds, `og:image` and
`Product.image` all need a picture, so a row of the gallery is always one. A video comes from one
of two places:

- **YouTube** — a link in any form it is shared in (`watch?v=`, `youtu.be/`, `/shorts/`, `/embed/`,
  `/live/`). Added to the gallery, it becomes a row of its own: the video's cover is the picture,
  and an empty `alt` is filled with the video's title.
- **A file of your own**, MP4 or WebM, stored as it is — no re-encoding, no ffmpeg on the server.
  The panel sends it through [chunked uploads](./uploads), so PHP's and nginx's limits never see
  the whole file; the limit that counts is `webx-catalog.videos.max_size_mb`. The type is checked
  by the content, so a picture renamed `.mp4` is refused.

A **direct link to a file** is downloaded on the queue by `FetchVideo`: the request answers `202`
at once and the video appears when the job is done. A site that adds videos by link needs a queue
worker. Replacing or removing a video deletes its file at once; deleting a picture deletes its
video.

**In the panel**, a video dropped into the upload zone gets its poster from a frame the browser
takes before anything is sent, then goes up in pieces with a bar, pause and cancel. A picture's
menu attaches a file or a link to it. Uploads belong to the editor, not the tab: the form can be
filled in meanwhile, leaving the page asks first, and an interrupted upload is offered again the
next time the product is opened — the same file continues from where the server stopped.

**On the storefront**, `product.blade.php` shows the poster with a ▶, and the player is put in only
on the click: `<video>` for a file, an iframe from `youtube-nocookie.com` for YouTube. Until then
nothing is loaded from YouTube and no cookie is set; without JavaScript the ▶ is a plain link. A
site that overrides the view keeps the classes `webx-catalog-product__video` and
`webx-catalog-product__play` and the attributes `data-webx-embed` / `data-webx-video` to reuse the
script.

```php
// config/webx-catalog.php
'videos' => ['max_size_mb' => 2048, 'types' => ['video/mp4', 'video/webm']],
```

`WEBX_CATALOG_VIDEO=false` takes the player and the markup off the storefront and the video menu
off the panel, and the API and the agent refuse to attach one; videos already attached are kept.

YouTube is the one provider in the core. Another is a class implementing `VideoProvider` — its
`key()`, `label()`, `idFrom($url)` (`null` when the link is not one of its videos), `embedUrl()`,
`watchUrl()`, `posterUrls()` (best first) and `title()` — registered from a provider:

```php
use WebxUi\Catalog\Gallery\Video\VideoProviders;

$this->app->make(VideoProviders::class)->register(new MyProvider);
```

Its links are then accepted everywhere a YouTube link is. The design is
`docs/architecture/WEBX_UI_CATALOG_VIDEO.md`.

## Properties

`module-catalog-properties` is where most filters come from: the screen size, the colour, the
material, the weight. A property has a **type**, chosen once:

| Type     | Value                           | As a filter                                     |
| -------- | ------------------------------- | ----------------------------------------------- |
| `select` | a value of its reference book   | terms; a tree of values when `is_tree` is on    |
| `number` | a number with a unit and digits | a slider, or intervals set by hand              |
| `text`   | a text per language             | none; shown and searched                        |
| `bool`   | yes or no                       | a toggle, with its own word for «yes» in an URL |

The flags say where it goes: `is_filterable` (a facet), `is_indexable` (a page of one value open to
search), `is_searchable`, `in_card`, `on_page`, `in_list`. A reference book can hold several values
per product (`is_multiple`), be a tree (`is_tree`, with `leaves_only` to forbid the middle of it),
be ordered alphabetically or by hand, and carry a colour or a picture per value that the filter
draws before the label. Values can be **merged**: the products move over and the old slug answers
`301` to the new one. The `code` is per language, from the name, and is what the address spells:
`/laptops/color_black`, `/de/laptops/farbe_schwarz`.

**Sets.** A category has properties of its own and inherits every ancestor's. A product shows,
filters and is found by the set of its **main** category; a value of a property outside the set is
kept and shown nowhere until the category has it again. The groups of a set — «Screen»,
«Processor» — are what the «Specifications» tab is divided by.

**Where no category decides** — the search, a brand's page, the root — the facets of properties
are picked by coverage: those held by at least `dynamic_facets.min_share` of the products found,
at most `dynamic_facets.limit` of them (`config/webx-catalog-properties.php`, `0.1` and `8`).

A page of one value is open to search only within a category. Its title is the property's
`seo_pattern` (`{category}`, `{property}`, `{value}`) or the catalogue's «{category} {value}». On
the storefront, the card prints the `in_card` properties, the product page has «Specifications»
with the `on_page` ones by group, and `$product->properties()` gives them to a template of your
own. The design is `docs/architecture/WEBX_UI_CATALOG_PROPERTIES.md`.

## Dictionaries: labels, stock, brands

Three small satellites, each a reference book with the same screens in the panel — the list in the
order of the site with a count of products per row, «Show its products», and the page of one record
— and each almost entirely registrations in the core's registries. Each adds a field to the «Main»
tab of the product form, a column and a filter to the products list, a bulk action, a field of the
search document, a column of the exchange and a place on the card.

**Labels** — top, sale, new. Several on a product. A label has a `code` (`label_sale` in an address,
one in every language), a tone (`neutral`, `primary`, `success`, `warning`, `danger`, `info`) that
the site turns into a class (`wx-catalog-badge--danger`) and paints itself, `is_badge` to draw it on
the card and `is_visible` to offer it in the filter — both off, it is a service label for templates.
Bulk actions: `add-label`, `remove-label`. A label still on products cannot be deleted.

**Stock** — in stock, out of stock, on order; the migration makes these three. One per product. A
status with «can be bought» off refuses the product with the code `stock` and its own name. A
product without a row is in the **default** status, so the module goes onto a live catalogue
without writing a row per product; the default cannot be deleted, and a status products are in
cannot either. Bulk action: `set-stock`. Quantities and a status that follows them are not in it.

**Brands** — one per product, each with a page of its own: `/brands/apple/` is the catalogue
narrowed to the brand, with a logo and a description on top, and the filter's tail after it
(`/brands/apple/category_laptops/`). `/brands/` lists the published ones. The prefix is
`webx-catalog-brands.prefix` (`WEBX_CATALOG_BRANDS_PREFIX`); after changing it, run
`php artisan webx:routes:rebuild --type=catalog.brand`. A brand's slug is unique across the site; a
hidden brand answers `404`, a deleted one `410`, a brand products are of cannot be deleted. The
`brand` facet is indexable — `/laptops/brand_apple/` is a page search engines may find. Bulk
action: `set-brand`. `brands()->featured()->take(8)` gives cards for a home page. The logo is
picked from the media library, so brands want `media()` in the panel.

The design of all three is `docs/architecture/WEBX_UI_CATALOG_DICTIONARIES.md`.

## Landing pages

A landing is a category's list — or the whole catalogue's — with a filter set chosen in advance,
under an address, an SEO card and texts of its own: `/apple-laptops/` instead of
`/laptops/brand_apple/`. It is the category's page with the filter already set, not a page built
of blocks; for that there are [pages](/guide/pages).

- **The set** is any facets, including several values of one facet and ranges («laptops under
  1000»). It is stored by value ids, so renaming a brand or a value does not touch it. Two landings
  with the same set on the same base are refused.
- **The address** is flat from the root, in the same space as categories; a new slug leaves the old
  one as a `301`.
- **Every link of the filter** that picks a landing's set points straight at the landing, and the
  category's spelling of the set answers `301` there. A choice on top of the set is written after it
  — `/apple-laptops/price_0-1000/` — and is closed, like any combination. The largest landing
  covering the choice wins.
- **The page** has the landing's SEO card, a text above the list and one under the pages, its own
  default sort (the reader's `?sort=` beats it) and a strip of recommended products picked by hand.
  An empty landing stays up, `noindex`, out of the sitemap.
- **Links between them:** «Collections» on a category (landings marked for it), neighbours on the
  same base, the same set in other categories, and «In collections» beside a product. Their limits
  are `webx-catalog-landings.links.*`.

**Values that go.** A merged value is followed silently. A deleted one drops out of the set and the
landing is marked «needs attention»; a set left empty, or turned into another landing's, is
unpublished. Saving the landing clears the mark.

In the panel, **Landings** lists them with their set as chips, the number of products and the mark.
The form counts the products live as the set is built. **Create in bulk** makes base × values in
one go — every brand in these categories — from patterns for the slug, the H1 and the SEO,
previewed as a table with the conflicts (slug taken, set taken, no products) skipped. Large runs go
to the queue. The number of products per landing is recounted after each indexing batch and nightly
by `webx:catalog-landings:count --all`. The design is
`docs/architecture/WEBX_UI_CATALOG_LANDINGS.md`.

## Import and export

Products go in and out as CSV or XLSX files. A column of a file is a field of the product form —
`sku`, `name`, `name@de` for a translation, `price`, `category` as a path of names, a label's code,
a brand, a property by its code — and every row is saved by the same form, with its checks and its
journal. A bad row is an error beside its number; its neighbours are written anyway. A file of up to
`exchange.sync_limit` rows is done in the request; a larger one goes to the queue in chunks.

«Exchange» is in the `···` of the products. It lists every run with what it did: its totals, the
first hundred errors (all of them as a CSV), the finished file of an export and, for an import, what
it changed in the journal.

**Import** is a wizard of three steps: a file — uploaded in pieces or downloaded by the server from
an address — then its columns matched to the catalogue's, with the first rows of the file under
each, then how to write: the key that finds a product, which rows to take, whether an empty cell
clears a field, what happens to products the file does not have, whether missing categories and
values are created, what pictures by address do. The last step checks the file without writing,
or runs it, and can save everything as a **profile** — the same supplier's price list is then one
step the next time.

**Export** writes what the products list has picked — ticked rows or everything a filter finds —
from «Actions», or the whole catalogue from «Exchange», with the columns of a profile or chosen on
the spot.

### The queue's `retry_after`

A large import is a chain of jobs: each does chunks for `exchange.job_seconds` (60) and hands the
rest to the next. The site's queue connection has a `retry_after` of its own (`config/queue.php`) —
how long a worker may hold a job before the queue decides it was lost and gives it to another
worker. Shorter than a job runs, it makes a second worker start the same job while the first is
still inside a chunk. Nothing is written wrong — a chunk is one transaction, and a row written again
writes itself — but the work is done twice and the run takes longer.

Keep `retry_after` above `job_seconds` with room for the last chunk: the default 90 of the
`database` and `redis` connections covers the default 60. Raise them together — `job_seconds` 300
wants `retry_after` around 360 — and keep the worker's `--timeout` below `retry_after`, as Laravel
asks of every job. On SQS the same number is the queue's visibility timeout.

The settings of an import and the head of a profile are the described screens
`catalog.exchange-import` and `catalog.exchange-profile`: a project takes a setting away or fixes it
with a patch. The design is `docs/architecture/WEBX_UI_MODULE_CATALOG_EXCHANGE.md`.

## The search engines

Lists, filters with their counts and the search are answered by an **engine**; the database stays
the place everything is written to, and a product page is always read from it.

**`SqlEngine`** is the database itself and the default. It needs nothing installed and is honest
up to a couple of thousand live products (`webx-catalog.sql_engine_limit`, 2000). Past that, `webx:doctor`
and the products list in the panel say so. Its search is `like` on the name, the article number and
the barcode.

**Manticore** comes with `webx-ui/module-catalog-manticore` and makes a Manticore Search server
(29 or later) the engine: a table per language with that language's morphology, facets and counts
in one round trip, codes found by any part of them (`34/5` finds `AT-1234/56`), a wrong keyboard
layout corrected, and a rebuild swapped in whole.

```bash
composer require webx-ui/module-catalog-manticore
pnpm add @webx-ui/module-catalog-manticore
```

```dotenv
WEBX_CATALOG_ENGINE=manticore
MANTICORE_HOST=127.0.0.1
MANTICORE_PORT=9308
# Required, no default: two sites on one server must not share tables.
MANTICORE_TABLE_PREFIX=shop
```

`php artisan webx:catalog:index --rebuild` fills the index once. From there it keeps itself
current: saving a product — from the form, an import, a satellite — marks it in a queue table, and
`webx:catalog:index` on the schedule writes the marked ones every minute, in batches. A satellite
that changes a dictionary marks the affected products with one statement. Saving never waits for
the engine and never fails with it.

**In the panel**, «System → Search index» (on the Manticore engine only) shows the server, each
language's table against the database, and the queue. A table whose schema is out of date has
«Rebuild» — a job that fills new tables beside the live ones and swaps them in; it needs a queue
worker. Looking needs `search-index.view`, rebuilding `search-index.manage`. Until the rebuild, an
old table is asked by what it has, and the list does not break.

**One rebuild at a time, wherever it starts.** `--rebuild` from the console and «Rebuild» in the
panel take the same lock: the second one is refused — the command exits with an error, the button
answers `409` — and while the console rebuilds, the page says so and holds the button back. The
lock lives in the cache, so web, worker and console need one store (`file`, `database`, `redis`;
not `array`). A process killed mid-rebuild leaves it for an hour at most.

The panel's rebuild is one job of up to `rebuild.timeout` (3600) seconds. Give it a queue of its
own (`MANTICORE_REBUILD_QUEUE`) on a connection whose `retry_after` is longer than that: with the
default 90 the queue hands the running rebuild to a second worker, which marks it failed while the
first is still writing.

**When the server does not answer**, the panel's list says so and answers from the database. The
storefront does the same up to `sql_engine_limit` products and answers `503` past it. The design is
`docs/architecture/WEBX_UI_CATALOG_MANTICORE.md`.

## Extending the catalogue

### The anatomy of a satellite

A satellite is a pair of packages, like any section of the panel, and it knows only the core; the
core knows none of them. It keeps its data in a table of its own with a `product_id` and never adds
a column to `catalog_products` — remove the module and its table goes, the product stays whole. It
then takes whichever of the core's points it needs, each a registry filled from its service
provider:

| Point             | What the satellite does                                   | Registry of the core                               |
| ----------------- | --------------------------------------------------------- | -------------------------------------------------- |
| the panel         | a dictionary section in the «Catalog» group               | a module of `module-admin`                         |
| the product form  | reads and writes **its** share in the form's transaction  | `ProductParts` + a patch of `catalog.product-form` |
| the products list | a column; the filter is its facet                         | `ProductColumns`, `Facets`                         |
| the index         | fields of the search document, a batch at a time          | `Documents` (`DocumentContributor`)                |
| the storefront    | a facet, a piece of the card, a narrowing of `products()` | `Facets`, `StorefrontParts`, `ProductQuery::macro` |
| bulk actions      | «set the brand» on forty thousand products                | `BulkActions`                                      |
| the exchange      | columns of CSV and XLSX                                   | `ExchangeColumns`                                  |
| buying            | a rule that may refuse                                    | `Purchasability`                                   |

The product form is one form that several modules write. The core opens a transaction, saves the
product, hands each `ProductPart` its share of the input (`rules()`, `write()`), and marks the
product for the engine once; any part failing rolls everything back. Reading is the same in reverse
— `read()` of each part fills the form. The part's fields are named `<part key>.<field>` on the
screen, and the changes it returns go into the product's one journal entry. A feature switched off —
a price without prices — registers no point at all.

### A dictionary of your own

The seams are proven by a dictionary no package has: a parts shop that wants the **manufacturer of
the machine** a part fits, beside the brand of the part itself. It is project code, and the core is
not touched. The pieces:

**The data** — a table of the shared category kind and a link table keyed by the product:

```php
Schema::create('manufacturers', function (Blueprint $table) {
    $table->id();
    $table->category();          // title, slug, is_visible, position, extra, soft deletes
});

Schema::create('catalog_product_manufacturer', function (Blueprint $table) {
    $table->foreignId('product_id')->primary()->constrained('catalog_products')->cascadeOnDelete();
    $table->foreignId('manufacturer_id')->index()->constrained('manufacturers')->cascadeOnDelete();
});
```

The model implements `Category` with `IsCategory`, refuses `_` in its slug, and on an edit that
changes what products show marks them all: `app(Catalog::class)->touchQuery($this->affectedProducts())`.

**The registrations**, in the site's provider:

```php
CategoryRoutes::register(Manufacturer::class, 'catalog/manufacturers');   // in a routes file under the panel's API
Screens::register('manufacturers.form', resource_path('screens/manufacturers.form.json'));
Screens::extend('catalog.product-form', resource_path('screens/catalog.product-form.json'));
$this->app->make(CategorySources::class)->register('catalog/manufacturers', Manufacturer::class);
$this->app->make(ModuleRegistry::class)->register($this->app->make(ManufacturersModule::class));

$this->app->make(ProductParts::class)->register(new ManufacturerPart);
$this->app->make(ProductColumns::class)->register(new ManufacturerColumn);
$this->app->make(ExchangeColumns::class)->register(new ManufacturerExchangeColumn);
$this->app->make(Facets::class)->register(new ManufacturerFacet);
$this->app->make(Documents::class)->register(new ManufacturerDocument);
```

- `ManufacturersModule` puts the section in the catalogue's group (`CatalogModule::GROUP`) and gives
  an agent its tools through `CategoryTools`.
- The patch adds a `wx-select` named `manufacturer.id` with `source: "catalog/manufacturers"` to the
  product form; `ManufacturerPart` reads and writes it.
- `ManufacturerFacet` extends `AbstractFacet`: `Terms`, its `key()` (`manufacturer`, so
  `/parts/manufacturer_bobcat`), `labels()`, `slugs()` and `resolveSlugs()` for the address,
  `applySql()` and `sqlValues()` for `SqlEngine`, and `indexable(): false` if its pages should stay
  closed. `OrderedFacet` keeps the order of the list instead of the alphabet.
- `ManufacturerDocument` puts the same field in the search document, so Manticore counts it too.
- `ManufacturerExchangeColumn` reads a cell by slug, name or `#id`.

**The panel half** is the core's shared dictionary screens, with the site's words:

```ts
import { dictionarySection } from '@webx-ui/module-catalog'

const manufacturers = dictionarySection({
  id: 'manufacturers',
  slug: 'manufacturers', // the path and the API: catalog/manufacturers
  screen: 'manufacturers.form',
  facet: 'manufacturer', // «Show its products» filters the list by it
  namespace: 'site-manufacturers',
  group: 'manufacturer',
  messages: { manufacturer: { new: 'New manufacturer' /* … */ } },
})

createAdmin({ modules: [...catalog(), manufacturers] })
```

The filter, its counts, the order of segments in the address, the page of one value, the column,
the bulk selection and the import then work as they do for brands. A dictionary the site only needs
in templates can stop at the data and `ProductQuery::macro()`.

A satellite **package** does the same from its own provider, and registers once more where every
section of the panel does: `Setup\Catalogue` in `module-admin` and `extra.webx` in its
`composer.json`. See [Extending](/guide/extending) for those.

## Agents

With `webx-ui/mcp`, each installed package is a set of tools for an [agent](/guide/agents), under
the scopes `catalog:read` and `catalog:write` and the permissions of whoever connected it. Every tool
that changes something accepts `dry_run: true`.

| Package    | Tools                                                                                                                                                                                                   |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| core       | `catalog_products_list`, `_get`, `_create`, `_update`, `_publish`, `_unpublish`, `_delete`, `_restore`; `catalog_categories_tree`, `_create`, `_update`, `_move`, `_delete`, `_restore`; `catalog_bulk` |
| gallery    | `catalog_products_gallery`, `_add`, `_update`, `_order`, `_video`, `_remove`                                                                                                                            |
| exchange   | `catalog_exchange_columns`, `catalog_import`, `catalog_export`, `catalog_exchange_run`, `catalog_exchange_profiles`                                                                                     |
| properties | `catalog_properties_*`, `catalog_property_values_*` (with `_merge`), `catalog_property_groups_*`, `catalog_categories_properties`, `_set`                                                               |
| labels     | `catalog_labels_list`, `_create`, `_update`, `_delete`, `_reorder`                                                                                                                                      |
| stock      | `catalog_stock_list`, `_create`, `_update`, `_delete`, `_reorder`                                                                                                                                       |
| brands     | `catalog_brands_list`, `_create`, `_update`, `_delete`, `_reorder`                                                                                                                                      |
| landings   | `catalog_landings_list`, `_get`, `_facets`, `_count`, `_create`, `_update`, `_publish`, `_unpublish`, `_delete`, `_restore`, `_generate`, `_generation`                                                 |
| manticore  | `catalog_index_status` — read only: a rebuild is minutes of load, and its time is a person's to choose                                                                                                  |

A product's share in a satellite is written through `catalog_products_update` by the part's key —
`brand.id`, `labels.ids`, `stock.status`, `properties.values` — and an unknown key is refused with
the list of known ones. Deleting needs `catalog.delete`, even with a write scope.

The resources are what an agent reads before writing: `catalog://facets`, `catalog://fields` (what
is switched on, video limits included), `catalog://addresses`, `catalog://categories`,
`catalog://product-parts`, `catalog://bulk-actions`, `catalog://exchange` (the file format on one
page — building the file right is cheaper than reading its errors), `catalog://properties` and
`catalog://landings` (when to make one, how not to make the same one twice). An agent has no upload:
a file it imports, or a picture it adds, has to live at an address the server can download.

## Config

`config/webx-catalog.php`, beyond what is above:

| Key                | Default              | What it is                                                       |
| ------------------ | -------------------- | ---------------------------------------------------------------- |
| `engine`           | `sql`                | `WEBX_CATALOG_ENGINE`; `manticore` with its package              |
| `sql_engine_limit` | `2000`               | where the database engine stops being enough                     |
| `per_page`         | `24`                 | products on a page of a list                                     |
| `images.*`         | `public`, 10 MB      | the gallery's disk and the size of one picture                   |
| `bulk.*`           | `500`, `50`          | the chunk of a bulk action; up to how many run without the queue |
| `exchange.*`       |                      | chunks, limits, the disk and how long files and runs are kept    |
| `layout`           |                      | `WEBX_CATALOG_LAYOUT`, the Blade component the views stand in    |
| `middleware`       | `web`, `webx.locale` | what the root and the search run through                         |

The schedule is the package's own: indexing every minute (only when the engine needs an index),
counted views flushed every five minutes, popularity nightly, the exchange's old files hourly. The site needs
`php artisan schedule:work` (or cron) and a queue worker, like any shop.
