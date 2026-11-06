# webx-ui/module-catalog-collections

Product collections for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel. A collection is a named block of products a manager puts together once, by hand or by a
filter. A satellite of `webx-ui/module-catalog`.

Specification: `docs/architecture/WEBX_UI_CATALOG_COLLECTIONS.md` in the monorepo.

This package is being built in stages. So far it holds the tables and the model: a collection can
be made in code. It does not print anything on the site yet and has no section in the panel.

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
  a collection in the bin and cannot be changed after creation.
- `limit` is from 1 to `webx-catalog-collections.max_limit` (48 by default).
- `link` is a `wx-link` value, read as a block's is: `javascript:` and other schemes that do not open
  a page are refused.
- `source` is `manual` or `filter`, and `view` is `grid` or `carousel`.

The hand-picked list is `$collection->products()`, in the order of its `position`. A product in the
bin keeps its row and is back in its place when restored.

## Config

| Key         | Env                                  | Default | What                            |
| ----------- | ------------------------------------ | ------- | ------------------------------- |
| `max_limit` | `WEBX_CATALOG_COLLECTIONS_MAX_LIMIT` | 48      | the ceiling of every collection |
