# webx-ui/module-catalog-collections

Product collections for the catalogue. A collection is a named block of products a manager puts
together once, by hand or by a filter. It has no address of its own: for a page with an address and
SEO, use `webx-ui/module-catalog-landings`. This package is a satellite of `webx-ui/module-catalog`;
for the filter, its facets and the product card, read the core's `AGENTS.md`. The package is being
built in stages: so far it holds the tables, the model, what a collection contains, the tag that
prints it, the block that places it, the shortcode, the section of the panel and the agent's tools.

## What it owns

- **Tables** `catalog_collections` (`WebxUi\CatalogCollections\Models\ProductCollection`) and
  `catalog_collection_products` (the hand-picked list, ordered by `position`). `title` and
  `link_text` are translatable. `filters` is a `WebxUi\Catalog\Filter\FacetSet`, stored by value ids.
- **Config** `webx-catalog-collections.max_limit`, the ceiling of every collection's `limit`.
- **Journal** type `catalog.collection`, read behind the catalogue's permissions.
- **Snapshots**: both tables are content and move with `webx:snapshot`.
- **Resolvers** `WebxUi\CatalogCollections\Resolvers\CollectionResolvers`: one class for each
  `source`, `ManualResolver` and `FilterResolver`. Both go through `products()`, so who may be seen
  is the catalogue's rule; `only_purchasable` is its `inStock()` step, skipped without the stock
  satellite.
- **Presets** `Catalog\CollectionPresets`: `new`, `on-sale`, `label`. A preset writes ordinary
  fields and is not stored; `of()` says which one the fields still match.
- **Reading** `Storefront\LiveCollections::find($key)` gives the enabled collection, and
  `Storefront\CollectionProducts::ids($collection, $limit, $locale)` its product ids. The `$limit`
  can only lower the collection's own.
- **Cache group** `catalog-collections`, moved by `ProductCollection`. The ids also depend on
  `catalog`, so a product, its price or the flag of a status needs nothing here.
- **Tag** `<x-webx-catalog-collections::collection key="popular" />`
  (`View\Components\Collection`, view `webx-catalog-collections::collection`): `view` is `grid` or
  `carousel`, `limit` can only lower the collection's own, `title` is empty for the collection's
  title and `-` for none. Off, in the bin, unknown or empty prints nothing. The card is
  `webx-catalog::card`. The link goes to a page, an address or a file of the media library
  (`props.files`); the view prints it with `LinkAttributes::of($link)`, `download` included.
- **Block** `product-collection` (`resources/blocks/product-collection.json`), offered under the
  module id `catalog-collections`, a thin template over the tag. It stands in a region like any
  block. Its field `wx-catalog-collection` (`Screens\CollectionFieldType`) keeps the key and refuses
  an unknown one only when it is written; the relation target `catalog-collection`
  (`Relations\CollectionTarget`) lists the collections, behind no permission.
- **Shortcode** `[product-collection key=cameras view=carousel limit=4]`
  (`Storefront\CollectionShortcode`): the tag in a rich text, a block shortcode of `module-admin`.
  In a line of text and in anything plain it prints nothing. The panel's `[` offers a row for each
  collection, so the key is chosen, not typed.
- **Panel** the section `catalog-collections` (`Panel\CollectionsModule`), the form
  `catalog.collection-form` and the API `/api/cms/catalog/collections`
  (`Http\CollectionController`). A write goes through `Http\CollectionForm`, which holds a filter to
  its facets and sort, stops the list at `max_limit` and clears `attention`. `POST preview`
  (`Catalog\CollectionPreview`) answers on unsaved values, with the reason a picked product is out.
  The npm half is `@webx-ui/module-catalog-collections`.
- **Usage** `Panel\CollectionUsage`: where a collection stands as a block, in pages and regions, in
  the live tree and in the draft, from one walk on the cache group `block-usage`. The list shows
  «Places: N», `GET {id}/usage` names them. Shortcodes in texts and tags in templates are not counted,
  and the panel says so. Without `webx-ui/module-blocks`, `places` is null and the panel shows neither.
- **MCP** tools `catalog_collections_list`, `_get`, `_facets`, `_preview`, `_usage`, `_create`,
  `_update`, `_products`, `_delete`, `_restore`, `_purge` (`Mcp\CollectionTools`), served through the core's
  `SatelliteTools`, so the names, scopes and permissions are the catalogue's. Resource:
  `catalog://collections` (the house rules). A set is codes and slugs (`Mcp\CollectionSet`), stored
  by ids. A write goes through `Http\CollectionForm`; a `dry_run` is that write rolled back.
- **Repairs** `Catalog\CollectionRepairs` listens to `FacetValueRetargeted`: a merge moves the set,
  a deletion marks `attention`, and a filter's set left empty turns the collection off.

### Permissions

None of its own: `catalog.view` reads the section and asks the preview, `catalog.manage` writes and
sends to the bin, restores and deletes for good. The section is visible to whoever holds either (`visibleWith()`). The tools follow:
`_list`, `_get`, `_facets`, `_preview` and `_usage` ask for either, under `catalog:read`; `_create`,
`_update`, `_products`, `_delete`, `_restore` and `_purge` ask for `catalog.manage`, under `catalog:write`.

## Change it without forking

| You want                      | Do this                                                            |
| ----------------------------- | ------------------------------------------------------------------ |
| A higher or lower ceiling     | `WEBX_CATALOG_COLLECTIONS_MAX_LIMIT`                               |
| Change the defaults in a file | `php artisan vendor:publish --tag=webx-catalog-collections-config` |
| Other words in the refusals   | `php artisan vendor:publish --tag=webx-catalog-collections-lang`   |
| Another way of filling one    | a `CollectionResolver` in `CollectionResolvers`, and its `source`  |
| Another preset                | `CollectionPresets::register()`                                    |
| Another markup                | `php artisan vendor:publish --tag=webx-catalog-collections-views`  |
| More on the form              | `Screens::extend('catalog.collection-form', […])`                  |
| Another card                  | override `webx-catalog::card`: it changes in the listing too       |

### Classes

- `webx-catalog-collection` — the section; `webx-catalog-collection--<view>` — `grid` or `carousel`.
- `webx-catalog-collection__head` — the heading and the link; `webx-catalog-collection__title`,
  `webx-catalog-collection__all`.
- `webx-catalog-collection__grid` — the list of the grid, beside the catalogue's `webx-catalog-grid`.
  `--webx-catalog-collection-card` is the narrowest card.
- `webx-catalog-collection__item` — a place for a card, in the grid and in the carousel.

## Do not

- Do not change `key` after creation: the model refuses it, because templates and texts name the
  collection by it. Make a new collection with the key you want instead.
- Do not reuse the key of a collection in the bin: it stays taken so that restoring never conflicts.
  Restore that collection, delete it for good, or choose another key.
- Do not add a foreign key on `category_id`: `nullOnDelete` would turn a collection of one category
  into a collection of the whole catalogue. A category that is gone gives an empty selection.
- Do not write `catalog_collections` with `DB::table()`: the checks of the key, the limit and the
  link live in the model. Save through `ProductCollection`.
- Do not write `catalog_collection_products` without saving the collection: the save moves the
  group. A path with no save calls `CacheGroups::bump('catalog-collections')`.
- Do not let a tool save what nothing reads: a condition on a manual collection and a list on a filter
  are refused in `Mcp\CollectionTools`, or an agent believes it changed what the site shows.
- Do not cache a list per `limit` of a place: the entry is on the collection's own limit, and a
  smaller one cuts it.

## Check your work

- `php artisan webx:demo --module=catalog-collections`: six collections, blocks on the home page and
  brackets in the first article that is out; `--remove` puts both back.
- `php artisan migrate`, then create one in `php artisan tinker`:
  `ProductCollection::query()->create(['title' => 'New arrivals'])->key` gives `new-arrivals`.
- A `limit` of 49 and a link to `javascript:alert(1)` throw a `ValidationException` under `limit`
  and `link`; so does a link to a file the media library does not have (`{ target: file, path }`).
- `app(CollectionProducts::class)->ids($collection)` gives the ids; unpublish one of the products and
  it is gone from the next call.
- Put `<x-webx-catalog-collections::collection key="new-arrivals" />` in a template: the cards print;
  turn the collection off and nothing does, not even the heading.
- `php artisan webx:blocks:offered --install --module=catalog-collections` gives the block type
  `product-collection`; a block with a key nobody has is refused on save, and the refusal lists the
  keys there are.
- Write `[product-collection key=new-arrivals]` alone in a paragraph of a rich text: the page has the
  section and no `<p>` around it; the same bracket in a title prints nothing.
- In the panel, «Catalog» → «Product collections»: on a new collection add products on the tab
  «Products», and the preview beside them says what the site would show before anything is saved.
- Put the block on a page and save the draft: the list says «Places: 1», and the words open the page's
  name, marked as a draft until it is published.
- In the tab «Bin», «Delete for good» asks first; the key then makes a new collection, and a block
  still naming it prints nothing until that one exists. `DELETE {id}/purge` of a live one is a 404.
- With MCP: read `catalog://collections`, then `catalog_collections_facets`, `_create` with `dry_run`, and
  `_preview`; `_update` with another `key` is refused, and says why.
- `vendor/bin/phpunit packages/module-catalog-collections/tests` in the monorepo's `php/`.

## Read more

- [README.md](README.md) in this directory: the model, its rules and the config.
- Guide: https://webx-ui.github.io/webx-ui/guide/catalog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_COLLECTIONS.md
- The core: `webx-ui/module-catalog` and its `AGENTS.md`.
