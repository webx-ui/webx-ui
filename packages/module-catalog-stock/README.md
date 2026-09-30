# @webx-ui/module-catalog-stock

Stock statuses for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel:
in stock, out of stock, on order — one per product, and the status says whether it can be bought.

The other half is the Composer package `webx-ui/module-catalog-stock`, which owns the statuses, the
field of the product form, the column and the facet of the list, the bulk action «Set the stock
status» and the rule of `Purchasability`. The section appears in the panel when both halves are
installed, beside `@webx-ui/module-catalog`.

## Install

```bash
npm install @webx-ui/module-catalog-stock
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import { catalogStock } from '@webx-ui/module-catalog-stock'
import '@webx-ui/module-catalog/style.css'
import '@webx-ui/module-catalog-stock/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...catalog(), ...catalogStock()],
}).mount()
```

`catalogStock()` is one section — the list of statuses, ordered by hand, and the page of one. It
stands in the «Catalog» group under «Dictionaries». The status field of the product form, the tag
in the list and the filter come through the catalogue's registries and need nothing here.
