# @webx-ui/module-catalog

The front end of the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel:
products with their gallery, a tree of categories with the filters each shows, and «Deleted».

The other half is the Composer package `webx-ui/module-catalog`, which owns the products, the
categories, their addresses, the search engine and the API. The section appears in the panel when
both halves are installed.

## Install

```bash
npm install @webx-ui/module-catalog
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import '@webx-ui/module-catalog/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...catalog()],
}).mount()
```

`catalog()` returns two sections, as the server registers two modules: `catalog` — the products,
with «Deleted» in the menu of their head — and `catalog-categories`, the tree. Spread it into
`modules`. The satellites (`module-catalog-*`) stand beside them in the `catalog` group of the
navigation.

The «Filters» tab of a category is off by default (`webx-catalog.fields.facets`): with the
category and the price alone there is nothing to arrange. A satellite that brings filters of its
own switches it on.

Keep the default path (`/catalog`): the server's refusal of a taken article number links to
`{panel}/catalog/products/{id}`.

## Screens

| Route                      | Screen                                                                     |
| -------------------------- | -------------------------------------------------------------------------- |
| `/catalog/products`        | the list: search, views, facets from `/facets`, satellites' columns, picks |
| `/catalog/products/{id}`   | `catalog.product-form` — a described screen, patched by satellites         |
| `/catalog/categories`      | the tree with the number of products in each branch, dragged into place    |
| `/catalog/categories/{id}` | `catalog.category-form`, with the «Filters» tab                            |
| `/catalog/deleted`         | «Deleted», products and categories, each with «Restore»                    |

The list keeps what it is looking at in the address — `view`, `q`, `sort`, `page`, and a
parameter per facet: `f.category=2,5`, `f.price=100-500`, `f.in-stock=1`.

## Screen node types

| Type                  | What it is                                                                    |
| --------------------- | ----------------------------------------------------------------------------- |
| `wx-catalog-category` | a category from the tree; `props.multiple` for several                        |
| `wx-catalog-facets`   | a category's own facet setting, or where it inherits one from                 |
| `wx-catalog-gallery`  | the product's pictures: added, ordered and captioned by requests of their own |

## License

MIT
