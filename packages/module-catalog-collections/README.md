# @webx-ui/module-catalog-collections

Product collections for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: a named block of products, picked by hand or by a filter, that the site prints wherever it is
placed: as a block, in a text or by a tag.

The other half is the Composer package `webx-ui/module-catalog-collections`, which owns the
collections, what they hold, the tag, the block and the shortcode. The section appears in the panel
when both halves are installed, beside `@webx-ui/module-catalog`.

## Install

```bash
npm install @webx-ui/module-catalog-collections
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import { catalogCollections } from '@webx-ui/module-catalog-collections'
import '@webx-ui/module-catalog/style.css'
import '@webx-ui/module-catalog-collections/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...catalog(), ...catalogCollections()],
}).mount()
```

`catalogCollections()` is one section, «Catalog» → «Product collections»:

- the list: title and key, how it is filled, the view, the state and the mark of attention; filtered
  by «needs attention», «turned off» and the bin; the key is copied with its tag;
- the page of one, new at `…/collections/new`: the title and the key, which cannot change after the
  first save, the limit, the view, the link «All products», and the products: a list found by name or
  SKU and put in order by dragging, or a filter with its presets, category, set, «on sale only» and
  sort; beside them, a live preview that says why a hand-picked product will not reach the site;
- the field `wx-catalog-collection` of the block `product-collection`.

The catalogue's `path` moves this section with it: `catalogCollections({ path: '/shop' })`.

## License

MIT
