# webx-ui/module-catalog-collections

Product collections for the catalogue. A collection is a named block of products a manager puts
together once, by hand or by a filter. It has no address of its own: for a page with an address and
SEO, use `webx-ui/module-catalog-landings`. This package is a satellite of `webx-ui/module-catalog`;
for the filter, its facets and the product card, read the core's `AGENTS.md`. The package is being
built in stages: so far it holds the tables, the model and what a collection contains, and it
prints nothing on the site.

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
- **Repairs** `Catalog\CollectionRepairs` listens to `FacetValueRetargeted`: a merge moves the set,
  a deletion marks `attention`, and a filter's set left empty turns the collection off.

## Change it without forking

| You want                      | Do this                                                            |
| ----------------------------- | ------------------------------------------------------------------ |
| A higher or lower ceiling     | `WEBX_CATALOG_COLLECTIONS_MAX_LIMIT`                               |
| Change the defaults in a file | `php artisan vendor:publish --tag=webx-catalog-collections-config` |
| Other words in the refusals   | `php artisan vendor:publish --tag=webx-catalog-collections-lang`   |
| Another way of filling one    | a `CollectionResolver` in `CollectionResolvers`, and its `source`  |
| Another preset                | `CollectionPresets::register()`                                    |

## Do not

- Do not change `key` after creation: the model refuses it, because templates and texts name the
  collection by it. Make a new collection with the key you want instead.
- Do not reuse the key of a collection in the bin: it stays taken so that restoring never conflicts.
  Restore that collection, or choose another key.
- Do not add a foreign key on `category_id`: `nullOnDelete` would turn a collection of one category
  into a collection of the whole catalogue. A category that is gone gives an empty selection.
- Do not write `catalog_collections` with `DB::table()`: the checks of the key, the limit and the
  link live in the model. Save through `ProductCollection`.
- Do not write `catalog_collection_products` without saving the collection: the save moves the
  group. A path with no save calls `CacheGroups::bump('catalog-collections')`.
- Do not cache a list per `limit` of a place: the entry is on the collection's own limit, and a
  smaller one cuts it.

## Check your work

- `php artisan migrate`, then create one in `php artisan tinker`:
  `ProductCollection::query()->create(['title' => 'New arrivals'])->key` gives `new-arrivals`.
- A `limit` of 49 and a link to `javascript:alert(1)` throw a `ValidationException` under `limit`
  and `link`.
- `app(CollectionProducts::class)->ids($collection)` gives the ids; unpublish one of the products and
  it is gone from the next call.
- `vendor/bin/phpunit packages/module-catalog-collections/tests` in the monorepo's `php/`.

## Read more

- [README.md](README.md) in this directory: the model, its rules and the config.
- Guide: https://webx-ui.github.io/webx-ui/guide/catalog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_COLLECTIONS.md
- The core: `webx-ui/module-catalog` and its `AGENTS.md`.
