# webx-ui/module-catalog-labels

Labels for the catalogue, such as top, sale and new. A product can have several. A label shows up
as a badge on the card, as a filter, and as a way to pick products in a template. It has no page
or address of its own. This package is a satellite of `webx-ui/module-catalog` and works only
through the core's registries. For products, the filter, the card and the engine, read the core's
`AGENTS.md`.

## What it owns

- **Tables** `catalog_labels` (`WebxUi\CatalogLabels\Models\Label`) and `catalog_label_product`.
  A label has a `code`, which appears in addresses as `label_sale` and is the same in every
  language. It also has a colour (`color`): one of six tones (`neutral`, `primary`, `success`,
  `warning`, `danger`, `info`), or a colour of its own as `#rrggbb` with `text_color` on it
  (`#rrggbb`, or null for black or white by WCAG contrast). And the flags `is_badge` (drawn on
  the card) and `is_visible` (offered in the filter).
- **Seam** `WebxUi\CatalogLabels\Catalog\Labels`: `of($product)` is every label of a product of the
  page (badges and service ones, in the reference book's order). The badges loaded them all in one
  query, kept in the storefront's cache (`labels`) until the `catalog` group moves.
- **View** `webx-catalog-labels::badges`, printed at the point `catalog.card.badges`. The tone
  becomes a class, `wx-catalog-badge--{tone}`, and the site's CSS decides the colour. A colour of
  its own becomes `wx-catalog-badge--custom` with `--webx-label-bg` / `--webx-label-fg` on the
  element, read by `resources/theme/catalog-labels.css`.
- **Panel**: module `catalog-labels` in the catalogue's group, under «Dictionaries»; screen
  `catalog.label-form`. The API is `catalog/labels` under the panel's API path. A label saved
  moves the `catalog` cache group.
- **What it adds to the core**: the node `labels-card` / field `labels.ids` in the `main` tab of
  `catalog.product-form`, a products-list column, the facet `label` (not indexable), the field
  `labels` of the search document, an exchange column, and the bulk actions `add-label` and
  `remove-label`. It also adds `products()->label('sale', 'new')`.
- **MCP** tools `catalog_labels_list`, `_get`, `_create`, `_update`, `_delete`, `_reorder`.
  `_create` and `_update` take `values` (`color`, `text_color`, `code`, flags); the answer
  carries every value.

### Permissions

None of its own: the catalogue's permissions (`webx-ui/module-catalog`) open it, and the section
shows to whoever holds `catalog.view` or `catalog.manage` (`visibleWith()`).

| Permission       | Opens                                                                                                                                      |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `catalog.view`   | labels read-only: the list and the form; `catalog_labels_list`, `catalog_labels_get`                                                       |
| `catalog.manage` | create, edit, reorder, delete and restore labels; the `labels.ids` field and the bulk actions `add-label`, `remove-label`; the write tools |

A route or screen node the site adds next to the module asks the same: `cms.can:catalog.view,catalog.manage`
to read, `cms.can:catalog.manage` to write.

## Change it without forking

| You want                            | Do this                                                                                       |
| ----------------------------------- | --------------------------------------------------------------------------------------------- |
| Badge colours of the site           | style `.wx-catalog-badge--{tone}` in the site's CSS                                           |
| One label in a brand colour         | `color` = `#rrggbb` (form: «Own colour»); `text_color` only if contrast picks wrong           |
| Different badge markup              | `php artisan vendor:publish --tag=webx-catalog-labels-views`, keep only `badges.blade.php`    |
| A service label only for templates  | switch off both `is_badge` and `is_visible`; select it with `products()->label('code')`       |
| A product's labels in a template    | `app(Labels::class)->of($product)`: badges and service ones, no query of its own              |
| Products with a label in a template | `products()->label('sale', 'new')->take(8)` (by code)                                         |
| A field on the label form           | a patch: `Screens::extend('catalog.label-form', [...])`; `project-fields` is the place for it |
| Other words in the panel            | `php artisan vendor:publish --tag=webx-catalog-labels-lang`                                   |

Label form node ids: `naming`, `title`, `code`, `color`, `text-color`, `is-badge`,
`is-visible`, `project-fields`.

### Classes

- `wx-catalog-badges` — the list of badges on a card.
- `wx-catalog-badge` — one badge; `wx-catalog-badge--<tone>` — its tone, the site paints it.
- `wx-catalog-badge--custom` — a colour of its own: `background` and `color` from
  `--webx-label-bg` / `--webx-label-fg` on the element. Restyle the shape, keep the variables.

## Do not

- Do not edit `vendor/webx-ui/module-catalog-labels`, and do not copy it into the site. If no row
  above fits, the package is missing a seam: say so.
- Do not hard-code colours for each label in the badge view. The tone is a class, so add the
  colour in the site's CSS; a one-off colour is the label's own `#rrggbb`, not a CSS rule.
- Do not delete a label that products still have (it is refused). First remove it with the bulk
  action `remove-label`.
- Do not rename a label's `code` on a live site without a reason. Templates that call
  `products()->label()` select by that code and would silently stop matching. Change the title
  instead.
- Do not query `Label` or `catalog_label_product` from a theme's template. `Labels::of()` already
  holds every label of the page, cached until the catalogue changes. A strip of extra products is
  told about with `StorefrontParts::extend()`, which loads their labels in one query.
- Do not set a product's labels through the labels API. Use `catalog_products_update` with
  `labels.ids`, or `catalog_bulk` with `add-label` / `remove-label`.

## Check your work

- A product with a label that has `is_badge` on shows the badge on its card.
  `/laptops/label_sale` filters the list, and the page is `noindex`.
- With MCP: run `catalog_labels_list`, then `catalog_products_get` on a product to see
  `labels.ids`. Every write takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory: what it adds to the catalogue.
- Guide: https://webx-ui.github.io/webx-ui/guide/catalog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_DICTIONARIES.md
- The core: `webx-ui/module-catalog` and its `AGENTS.md`.
