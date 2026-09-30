# @webx-ui/module-catalog-brands

Brands for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: a logo,
a description and a page of their own, one per product, a choice of the filter.

The other half is the Composer package `webx-ui/module-catalog-brands`, which owns the brands, their
addresses and pages, the field of the product form, the column and the facet of the list, and the
bulk action «Set the brand». The section appears in the panel when both halves are installed,
beside `@webx-ui/module-catalog`.

## Install

```bash
npm install @webx-ui/module-catalog-brands
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import { catalogBrands } from '@webx-ui/module-catalog-brands'
import { media } from '@webx-ui/module-media'
import '@webx-ui/module-catalog/style.css'
import '@webx-ui/module-catalog-brands/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...catalog(), ...catalogBrands(), media()],
}).mount()
```

`catalogBrands()` is one section — the list of brands in the order of the site, and the page of
one: «Main» (name, address, logo, published, featured), «Description», «SEO» and «History». The
logo is picked from the library of `@webx-ui/module-media`. The brand field of the product form, the
column and the filter of the list come through the catalogue's registries and need nothing here.
