# @webx-ui/module-catalog-labels

Labels for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: top,
sale, new — a badge on the card, a choice of the filter, `products()->label('sale')` in a template.

The other half is the Composer package `webx-ui/module-catalog-labels`, which owns the labels, the
field of the product form, the column and the facet of the list, and the bulk actions «Add a label»
and «Remove a label». The section appears in the panel when both halves are installed, beside
`@webx-ui/module-catalog`.

## Install

```bash
npm install @webx-ui/module-catalog-labels
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import { catalogLabels } from '@webx-ui/module-catalog-labels'
import '@webx-ui/module-catalog/style.css'
import '@webx-ui/module-catalog-labels/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...catalog(), ...catalogLabels()],
}).mount()
```

`catalogLabels()` is one section — the list of labels, ordered by hand, and the page of one. It
stands in the «Catalog» group under «Dictionaries». The field in the product form, the column of
badges and the filter of the list come through the catalogue's registries and need nothing here.
