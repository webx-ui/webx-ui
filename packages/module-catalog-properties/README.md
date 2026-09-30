# @webx-ui/module-catalog-properties

Properties of products for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: reference books (flat or a tree, with colours and pictures), numbers with units, texts and
yes/no; sets inherited down the categories; the specifications of a product.

The other half is the Composer package `webx-ui/module-catalog-properties`, which owns the
properties, their values and intervals, the sets, the facets of the filter, the index document and
the storefront's specifications. The section appears in the panel when both halves are installed,
beside `@webx-ui/module-catalog`.

## Install

```bash
npm install @webx-ui/module-catalog-properties
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import { catalogProperties } from '@webx-ui/module-catalog-properties'
import { media } from '@webx-ui/module-media'
import '@webx-ui/module-catalog/style.css'
import '@webx-ui/module-catalog-properties/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...catalog(), ...catalogProperties(), media()],
}).mount()
```

`catalogProperties()` is one section, «Catalog» → «Dictionaries» → «Properties»:

- the list, in the order of the filter's blocks, with a filter by type and group, the number of
  products on each line and «Deleted» with a restore;
- the page of one property — «Main» (only the fields its type has, and a number printed as the
  site will print it), «Values» (a lazy tree with a search, colours and pictures, merging) and
  «Intervals» (the steps a number is filtered by);
- the groups of the card, behind the «Groups» button.

It also draws the two tabs the server patches onto the catalogue's screens: «Properties» of a
category (its set, inherited from the categories above) and «Specifications» of a product (the set
of its main category, read again as soon as the category changes). The pictures of values come from
the library of `@webx-ui/module-media`.

The catalogue's `path` moves this section with it: `catalogProperties({ path: '/shop' })`.
