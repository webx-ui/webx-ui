# webx-ui/module-catalog-collections

Product collections for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel. A collection is a named block of products a manager puts together once, by hand or by a
filter. A satellite of `webx-ui/module-catalog`.

Specification: `docs/architecture/WEBX_UI_CATALOG_COLLECTIONS.md` in the monorepo.

This package is being built in stages. So far a collection can be made in code, in its section of
the panel or by an agent (`catalog_collections_*`, `catalog://collections`), says which products it
holds, prints with a tag, stands on a page as a block and goes into a text as a shortcode.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-catalog` and what it requires

## Install

```bash
composer require webx-ui/module-catalog-collections
php artisan migrate
```

## In code

```php
use WebxUi\CatalogCollections\Models\ProductCollection;

$collection = ProductCollection::query()->create([
    'title' => ['en' => 'New arrivals'],
    'source' => ProductCollection::FILTER,
    'limit' => 12,
    'link' => ['target' => 'url', 'url' => '/catalog/new'],
]);

$collection->key; // new-arrivals
```

The model refuses what it cannot keep with a `ValidationException` under the field's name:

- `key` is `[a-z0-9-]`, up to 64 characters, made from the title when none is given. It stays taken by
  a collection in the bin until that one is deleted for good, and cannot be changed after creation.
- `limit` is from 1 to `webx-catalog-collections.max_limit` (48 by default).
- `link` is a `wx-link` value, read as a block's is: `javascript:` and other schemes that do not open
  a page are refused.
- `source` is `manual` or `filter`, and `view` is `grid` or `carousel`.

The hand-picked list is `$collection->products()`, in the order of its `position`. A product in the
bin keeps its row and is back in its place when restored.

## On the site

```blade
<x-webx-catalog-collections::collection key="popular" />
<x-webx-catalog-collections::collection key="new" view="carousel" :limit="4" title="-" />
```

- `key` is required. `view` is `grid` or `carousel`; anything else is dropped for the collection's own.
- `limit` can only lower the collection's own limit.
- `title` is the heading: empty is the collection's title, `-` is none.

A collection that is off, in the bin, unknown or left empty prints nothing, not even the heading. The
cards are the catalogue's `webx-catalog::card`, and the carousel is `<x-webx-slider>`. A theme gets the
base look from `resources/theme/catalog-collections.css` after `php artisan webx:theme:sync`.

## As a block

`php artisan webx:blocks:offered --install` puts the block type `product-collection` on the site. It
goes on a page or in a region like any other block, and its template is the tag above.

| Field        | Type                    | Empty means                      |
| ------------ | ----------------------- | -------------------------------- |
| `collection` | `wx-catalog-collection` | no collection, nothing is drawn  |
| `title`      | `wx-input`, localized   | the collection's title; `-` none |
| `view`       | `wx-select`             | the collection's view            |
| `limit`      | `wx-input-number`       | the collection's limit           |

`limit` can only lower the collection's own. A key no collection has is refused when it is written,
and the refusal lists the keys there are. A collection put in the bin later does not stop the page
from being saved, and restoring it brings the block back.

## In a text

```text
[product-collection key=cameras]
[product-collection key=cameras view=carousel limit=4]
```

Works in a rich text: a text block of a page or an article, a description of a category, a brand or a
product. In the panel, `[` offers a row for each collection, so the key is picked rather than typed.
Alone in a paragraph, it replaces the paragraph; with text beside it, the paragraph is cut in two around
it. `limit` can only lower the collection's own. A title, a meta description, a feed or an excerpt
prints nothing for it. A typo in the key is not told from a collection turned off: both print nothing.

## What a collection holds

```php
use WebxUi\CatalogCollections\Storefront\CollectionProducts;
use WebxUi\CatalogCollections\Storefront\LiveCollections;

$collection = app(LiveCollections::class)->find('new-arrivals'); // null when off, binned or unknown

$ids = app(CollectionProducts::class)->ids($collection);    // up to its own limit
$ids = app(CollectionProducts::class)->ids($collection, 4); // a place can only lower it
```

A manual collection reads its list and a filter collection its category, set, `on_sale` and `sort`.
Both show only what the catalogue may show. With `only_purchasable`, on by default, they also leave
out what a status says cannot be bought; without `webx-ui/module-catalog-stock` that step is skipped.
The limit counts what is left, so a product taken off sale gives its place to the next one.

The ids are cached on the groups `catalog` and `catalog-collections`, one entry for each collection
and language. A write to the hand-picked list goes with a save of the collection, which moves the
group; a path without one calls `CacheGroups::bump('catalog-collections')`.

## Presets

`CollectionPresets` fills the ordinary fields; nothing about a preset is stored.

```php
$presets = app(CollectionPresets::class);

$fields = $presets->fields($presets->find(CollectionPresets::NEW));
$fields = $presets->fields($presets->find(CollectionPresets::ON_SALE));
$fields = $presets->fields($presets->find(CollectionPresets::LABEL), [$label->id]);

$presets->of($collection)?->key; // the preset the fields still match; null for a selection of its own
```

`label` is offered only when `webx-ui/module-catalog-labels` is installed. A preset leaves the
category alone.

## When a value of the set goes

A merged value is replaced in the set. A deleted one leaves it and sets `attention` to
`value-deleted`, and a filter collection whose set is left empty is turned off, so that it does not
become the whole catalogue. A manual collection does not read its set and only loses the value.

## Config

| Key         | Env                                  | Default | What                            |
| ----------- | ------------------------------------ | ------- | ------------------------------- |
| `max_limit` | `WEBX_CATALOG_COLLECTIONS_MAX_LIMIT` | 48      | the ceiling of every collection |
