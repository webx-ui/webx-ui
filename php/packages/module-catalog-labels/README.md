# webx-ui/module-catalog-labels

Labels for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel — top,
sale, new. Several on a product, a badge on the card, a filter with a multiple choice, and
`products()->label('sale')` in a template. A satellite of `webx-ui/module-catalog`: almost all of it
is registrations in the core's registries.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-catalog` and what it requires

## Install

```bash
composer require webx-ui/module-catalog-labels
php artisan migrate
```

The panel shows **Labels** in the «Catalog» group, under the «Dictionaries» caption. There are no
permissions of its own: `catalog.view` reads the list, `catalog.manage` writes it and puts labels
on products.

## A label

| Field        | What it is                                                                        |
| ------------ | --------------------------------------------------------------------------------- |
| `title`      | The name, per language.                                                           |
| `code`       | `[a-z0-9-]`, one for every language: `label_sale` in a filter address.            |
| `color`      | A tone — `neutral`, `primary`, `success`, `warning`, `danger`, `info`.            |
| `is_badge`   | Drawn as a badge on the card.                                                     |
| `is_visible` | A choice of the filter. Off together with `is_badge`, the label is a service one. |

A label that is still on products cannot be deleted: take it off first, with the bulk action
**Remove a label**. An edit that changes what the products show marks them for the search engine in
one statement.

## What it adds to the catalogue

- the field `labels.ids` of the product form, in the «Main» tab;
- a column of the list of products, the facet `label` (not indexable), the field `labels` of the
  search document;
- the bulk actions `add-label` and `remove-label`;
- the badges in `catalog.card.badges` — the view `webx-catalog-labels::badges`, published with
  `--tag=webx-catalog-labels-views`; the tone is a class (`wx-catalog-badge--danger`), the site decides
  the colour;
- `products()->label('sale', 'new')` — the products with any of these labels, by code.

To an agent: `catalog_labels_list`, `_create`, `_update`, `_delete`, `_reorder`; a product's labels
are written by `catalog_products_update` with `labels.ids`.

## License

MIT
