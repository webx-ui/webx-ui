# webx-ui/module-services

A catalogue of services as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: services made of blocks, flat categories that are pages of the site, addresses from the
registry and full SEO.

A service is built like a page. The address is `webx-ui/routing`, the content is
`webx-ui/module-blocks`, the draft and the history and the categories are `webx-ui/module-admin`,
what a page says about itself is `webx-ui/module-seo`, the covers are `webx-ui/module-media`, the
languages are `webx-ui/localization`. What this package adds is the catalogue around it.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-blocks`, `webx-ui/module-media`, `webx-ui/module-seo`,
  `webx-ui/routing`, `webx-ui/localization`, `webx-ui/mcp`

## Install

```bash
composer require webx-ui/module-services
php artisan migrate
```

Permissions: `services.view`, `services.manage`, `services.categories.manage`.

## Addresses

Two types in the registry, **on one level** under one prefix:

| Type               | Address                                 |
| ------------------ | --------------------------------------- |
| `service`          | `services/implants-turnkey`             |
| `service-category` | `services/implants`                     |
| the index (route)  | `services`, named `webx.services.index` |

The category is not part of a service's address: a service can be in three categories, and "which
of the three" has no answer. A category and a service that want the same slug are refused — the
second of them under its own field, with the name of the one that took it.

The prefix is `webx-services.prefix`. An empty one puts both types at the root beside the pages
and registers no index: `/` belongs to the site. Changing it afterwards:

```bash
php artisan webx:routes:rebuild --type=service --type=service-category
```

The old paths stay behind as aliases that redirect.

## Two orders

`position` on the service is the order of the whole list. `item_position` on the link is its place
inside one category. The panel drags whichever list the editor is looking at: the whole list
without a filter, one category with it. A service newly filed into a category takes its place
there by the whole list, so a category nobody rearranged lists its services exactly as the list
does.

The index lists every visible category in its order, each with its services in the order of that
category, and then the services no visible category lists. A category page lists its own.

```php
Service::query()->visible()->orderedIn($category->id)->get();   // one category's order
Service::query()->visible()->orderedIn()->get();                // the whole list
```

The first category of a service is its main one: it goes in the breadcrumbs.

## Visibility

A service is on the site when it is published and not in the bin; a category when it is visible
and not in the bin. The handlers and the sitemap ask the same `Visible` contract, so a draft is a
404 and a missing line in the map at the same moment. A service in a hidden category still answers
at its own address — it is not the category's property.

A category with services in it refuses to go into the bin, and says how many.

## Fields of the project

A site adds its own fields with a patch on `services.form` (or `services.category-form`), not with
a migration. Each screen has a card with the id `project-fields` for them:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": {
      "id": "price-from",
      "type": "wx-input-number",
      "name": "price-from",
      "label": "Price from"
    }
  }
]
```

The value lives in the `extra` column — through the draft for a service, at once for a category —
and is read the way its field type says: a localized field in the language of the page, a picture
as an address rather than a library key.

```blade
{{ $service->extra('price-from') }}
```

## SEO

Both models carry an SEO card (`HasSeo`), breadcrumbs (index → main category → service) and a
place in the sitemap. A service describes itself as a schema.org `Service`, naming the site's
`Organization` as its provider by `@id`. A category page and the index push an `ItemList` of what
they list.

## Views

Three views, each with the package's own underneath until the site writes its:

| Config key                     | Default             | Handed                                            |
| ------------------------------ | ------------------- | ------------------------------------------------- |
| `webx-services.views.index`    | `services.index`    | `$categories` (with `services`), `$uncategorised` |
| `webx-services.views.category` | `services.category` | `$category`, `$services`                          |
| `webx-services.views.service`  | `services.service`  | `$service`, `$category` (the main one)            |

`webx-services.layout` names the site's layout component (`<x-layout>`); empty prints a bare
document. `webx-services.categories.blocks` gives a category page a Blocks tab in the panel — off
by default, because a rewritten view may not print them.

```bash
php artisan vendor:publish --tag=webx-services-views
```

## In a block

`services()` gives a block template the services as cards — never what a reader may not see:

```blade
@foreach (services()->in('implants')->except($service)->take(6) as $card)
    <a href="{{ $card['url'] }}">{{ $card['title'] }}</a>
@endforeach
```

`in()`, `only()`, `except()`, `take()`, `locale()`, `categories()`. A card is `id`, `anchor`,
`categories`, `title`, `url`, `lead`, `cover` and the project's `fields`. The same cards come out of
the `services` source of a `wx-collection` field, and the module offers a block of them:
`php artisan webx:blocks:offered --install --module=services`.

## License

MIT
