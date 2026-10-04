# webx-ui/module-catalog-properties

Typed properties of products, such as screen size, colour and weight, together with their values,
their groups and a set of properties per category. Most of a catalogue's filters come from here.
This package is a satellite of `webx-ui/module-catalog` and works only through the core's
registries. Its agent tools are served under the catalogue's name and scopes. For products,
categories, the filter address, the storefront and the engine, read the core's `AGENTS.md`.

## What it owns

- **Tables** `catalog_properties` (`WebxUi\CatalogProperties\Models\Property`),
  `catalog_property_groups` (`PropertyGroup`), `catalog_property_values` (`PropertyValue`),
  `catalog_property_intervals`, `catalog_category_property` (a category's own set) and
  `catalog_product_property_values`.
- **A property** has a `type`: `select`, `number`, `text` or `bool`. The type is chosen once. A
  property's `code` is set per language and is what the address spells (`/laptops/color_black`).
  Its flags are `is_multiple`, `is_tree`, `leaves_only`, `is_filterable`, `is_indexable`,
  `is_searchable`, `in_card`, `on_page`, `in_list`, `has_color` and `has_image`. It also has
  `filter_mode` (`slider` or `intervals`), `value_order` (`alpha` or `manual`), `unit_prefix`,
  `unit_suffix`, `precision`, `toggle_slug` and `seo_pattern`.
- **Sets**: a category has its own properties and inherits every ancestor's. A product shows,
  filters and is found by the set of its **main** category.
- **Views** `webx-catalog-properties::card` (at `catalog.card.meta`, the `in_card` properties)
  and `webx-catalog-properties::table` (at `catalog.product.tabs`, «Specifications», the
  `on_page` properties grouped).
- **Panel**: module `catalog-properties` in the catalogue's group, under «Dictionaries»; screens
  `catalog.property-form` (tabs `main`, `values-tab`, `intervals-tab`, `history-tab`) and
  `catalog.property-group-form`. It patches `catalog.product-form` (tab `properties-tab`, field
  `properties-values`) and `catalog.category-form` (tab `properties-tab`, field
  `category-properties`). The API is `catalog/properties`, `catalog/property-groups`,
  `catalog/categories/{id}/properties` and `catalog/property-sets/{category}`. It has no
  permissions of its own: `catalog.view` reads and `catalog.manage` writes.
- **What it adds to the core**: the product-form field `properties.values`; a facet for each
  filterable property; products-list columns; search document fields; exchange columns by
  property code; the bulk actions `set-property`, `remove-property` and `clear-outside-set`;
  `products()->property('color', 'black')`; and `$product->properties()`.
- **MCP** tools `catalog_properties_*` (list, get, create, update, delete, restore, reorder),
  `catalog_property_values_*` (list, create, update, move, merge, delete),
  `catalog_property_groups_*`, `catalog_categories_properties` and `_set`. Resource:
  `catalog://properties`.

## Change it without forking

| You want                                               | Do this                                                                                                     |
| ------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| A new filter                                           | a property with `is_filterable`, added to the category's set (panel or `catalog_categories_properties_set`) |
| Pages of one value open to search                      | `is_indexable` on the property; its title from `seo_pattern` (`{category}`, `{property}`, `{value}`)        |
| Filters where no category decides (search, brand page) | `WEBX_CATALOG_PROPERTIES_MIN_SHARE` / `WEBX_CATALOG_PROPERTIES_LIMIT` (`dynamic_facets.*`)                  |
| Different «Specifications» or card markup              | `php artisan vendor:publish --tag=webx-catalog-properties-views`, keep only what you change                 |
| Properties in a template of your own                   | `$product->properties()` (label, formatted, value, code, group, values, url)                                |
| Products by a property in a template                   | `products()->property('color', 'black')`, `->property('weight', min: 1, max: 2)`                            |
| A field on the property form                           | a patch: `Screens::extend('catalog.property-form', [...])`; `project-fields` is the place for it            |
| Other words in the panel                               | `php artisan vendor:publish --tag=webx-catalog-properties-lang`                                             |

Property form node ids: `naming`, `title`, `code`, `type`, `group-id`, `values-shape`, `filter`,
`showing`, `project-fields`, `values`, `intervals`, `history`.

## Do not

- Do not try to change a property's `type` (it is refused). Product values have the old type's
  shape. Make a new property and move the values to it.
- Do not delete a value and create a duplicate to fix a spelling or a double. Use
  `catalog_property_values_merge` (or «Merge» in the panel). It moves the products, keeps the old
  slug as a 301, and keeps landing pages that use the value working.
- Do not give a property a code that a core exchange column already uses (`price`, `sku`, …).
  The file column gets renamed, and a supplier's file could fill the wrong field.
  `php artisan webx:doctor` reports such codes.
- Do not write values of properties that are outside the main category's set and expect them to
  show. They are kept but shown nowhere. Add the property to the set first.
- Do not write `catalog_product_property_values` with SQL. Neither the engine nor the landings
  learn about it. Use `catalog_products_update` with `properties.values`, or the bulk actions.

## Check your work

- On a category whose set has the property, the filter shows its values with counts, and
  «Specifications» lists the `on_page` properties on a product page.
- `php artisan webx:doctor` for code clashes; with Manticore, `webx:catalog:index` writes the
  changed products.
- With MCP: read `catalog://properties` first, then `catalog_categories_properties` for a set.
  Every write takes `dry_run: true`.

## Read more

- [README.md](README.md) in this directory: fields of a property, sets, the form, the API, the storefront.
- Guide: https://webx-ui.github.io/webx-ui/guide/catalog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_PROPERTIES.md
- The core: `webx-ui/module-catalog` and its `AGENTS.md`.
