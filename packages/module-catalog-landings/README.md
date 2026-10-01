# @webx-ui/module-catalog-landings

Landing pages for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel:
a category's list — or the whole catalogue's — with a set of filters chosen in advance, under an
address, texts and an SEO card of its own, with recommended products.

The other half is the Composer package `webx-ui/module-catalog-landings`, which owns the landings,
their addresses, the rewriting of the filter's links, the storefront's parts, the counts and the
generation. The section appears in the panel when both halves are installed, beside
`@webx-ui/module-catalog`.

## Install

```bash
npm install @webx-ui/module-catalog-landings
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import { catalogLandings } from '@webx-ui/module-catalog-landings'
import { seo } from '@webx-ui/module-seo'
import '@webx-ui/module-catalog/style.css'
import '@webx-ui/module-catalog-landings/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...catalog(), ...catalogLandings(), seo()],
}).mount()
```

`catalogLandings()` is one section, «Catalog» → «Landings»:

- the list — name and address, the base, the set as chips («Brand: Apple, Dell», «Price: – 50000»),
  the products, the state and the mark of attention — filtered by the base as a tree (the whole
  catalogue is a choice of its own), «needs attention», published, empty and the bin;
- the page of one landing, new at `…/landings/new`: the base, the set builder (a facet the base
  offers, its values in the controls of the products' filter, the live number of products and a
  warning when another landing holds the set), the address with the slug the set suggests, the
  texts above and under the list, the default sort, the collections of the category, the
  recommended products found by name or SKU and put in order by dragging, the SEO card and the
  history;
- «Create in bulk»: bases × the values of one facet, named by templates (`{category}-{value}`),
  previewed as a table where the rows that would clash — the address or the set taken, no
  products — are skipped, then made at once or by the queue with its progress.

The SEO card is `@webx-ui/module-seo`'s `wx-seo`, which a panel with landings has. The catalogue's
`path` moves this section with it: `catalogLandings({ path: '/shop' })`.

## License

MIT
