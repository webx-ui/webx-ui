# webx-ui/module-catalog-properties

Properties of products for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: reference books (flat or a tree), numbers with units, texts and yes/no; sets of properties
inherited down the categories; facets from the database, picked by coverage where no category
decides them; merging of values; the properties in the product form, the search and the index. A
satellite of `webx-ui/module-catalog`.

The full design is `docs/architecture/WEBX_UI_CATALOG_PROPERTIES.md` in the monorepo.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-catalog` and what it requires

## Install

```bash
composer require webx-ui/module-catalog-properties
php artisan migrate
```

No permissions of its own: `catalog.view` reads, `catalog.manage` writes.

## A property

| Field                                          | What it is                                                                   |
| ---------------------------------------------- | ---------------------------------------------------------------------------- |
| `title`                                        | The name, per language.                                                      |
| `code`                                         | `[a-z0-9-]` per language: `color_black`, `/ru/…/cvet_chernyy`. From the name |
| `type`                                         | `select`, `number`, `text`, `bool` — chosen once.                            |
| `is_multiple`, `is_tree`, `leaves_only`        | A reference book of several values, of values in a tree, of leaves only.     |
| `is_filterable`, `filter_mode`, `is_indexable` | A facet; a number by slider or intervals; a page of one value open to search |
| `is_searchable`                                | The value is found by the search.                                            |
| `in_card`, `on_page`, `in_list`                | Where it is shown.                                                           |
| `unit_prefix`, `unit_suffix`, `precision`      | How a number is shown: `⌀12 mm`, `1.35 kg`.                                  |
| `value_order`, `has_color`, `has_image`        | Values alphabetical or by hand; with a colour, a picture.                    |
| `toggle_slug`, `seo_pattern`                   | The word for «yes» in an address; the title of a page of one value.          |

Flags the type has no use for are put out on save. A code taken by another facet in the same
language is refused; a renamed code or slug keeps the old address as a 301.

## The set of a category

A category's properties are those of every ancestor, then its own. A product shows, filters and is
found by the set of its **main** category; a value of any other property is kept and shown nowhere
until the category has it again.

## The product form

One field, `properties.values`: `{ "<property_id>": value }` — an id or ids, a number, `true`, or a
map of languages. A value of a reference book may also be its slug in any language. A key left out
is not touched, `null` takes the value away.

## To an agent

The tools are served as the catalogue's, behind its `catalog:read` / `catalog:write` scopes:
`catalog_properties_list`, `_get`, `_create`, `_update` (with a number's intervals), `_delete`,
`_restore`, `_reorder`; `catalog_property_values_list`, `_create`, `_update`, `_move`, `_merge`,
`_delete`; `catalog_property_groups_*`; `catalog_categories_properties` and `_set`. The resource
`catalog://properties` says, per type, which flags and fields it keeps. A product's values are
`properties.values` in `catalog_products_update`.

## API

Under the panel's API path: `catalog/properties` (list, create, read, edit, reorder, bin, restore,
intervals), `catalog/properties/{id}/values` (lazy tree, search, create, edit, move, merge,
delete), `catalog/property-groups` (the shared category screens), `catalog/categories/{id}/properties`
(a category's own set and what it inherits) and `catalog/property-sets/{category}` (the set in force,
by group, for the product form).

## The storefront

- **A card** (`catalog.card.meta`): the properties `in_card`, in the order of the set — view
  `webx-catalog-properties::card`.
- **The page of a product** (`catalog.product.tabs`): «Specifications», the properties `on_page` by
  group — view `webx-catalog-properties::table`. A value of a reference book links to its page of
  one value where that page is open. Publish the views with `--tag=webx-catalog-properties-views`.
- **The filter** draws a value's colour or picture before its label.
- **A page of one value** (`/laptops/color_black`) is open to search only in a category; on a
  brand's page and at the root it is `noindex, follow`. Its title is the property's `seo_pattern`
  (`{category}`, `{property}`, `{value}`) or the catalogue's «{category} {value}». Open pages with
  products are in the catalogue's `catalog-filters` sitemap.
- **A template:**

```blade
@foreach (products()->property('color', 'black')->property('weight', min: 1, max: 2)->take(8) as $card)
    <a href="{{ $card['url'] }}">{{ $card['name'] }}</a>
@endforeach

@foreach ($product->properties() as $property)
    {{ $property->label }}: {{ $property->formatted }}  {{-- also value, code, group, values, url --}}
@endforeach
```

`property()` takes the code in the language read (or the id), then a slug or slugs (or ids), a
`min` / `max` for a number (both ends included), nothing for «yes» or «has any value».

## Configuration

`config/webx-catalog-properties.php` — `dynamic_facets.min_share` (0.1) and `dynamic_facets.limit`
(8): how many facets of properties stand open in the search, on a brand's page and at the root.

## License

MIT
