# @webx-ui/module-catalog-manticore

The search index of the [WebX UI](https://github.com/webx-ui/webx-ui) catalogue, in the admin
panel: «System → Search index».

The other half is the Composer package `webx-ui/module-catalog-manticore`, which makes Manticore
Search the catalogue's engine — a table per language, facets and counts in one round trip, a
rebuild swapped in whole, and the database to fall back on. The section appears in the panel only
on that engine (`WEBX_CATALOG_ENGINE=manticore`).

## Install

```bash
npm install @webx-ui/module-catalog-manticore
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import { catalogManticore } from '@webx-ui/module-catalog-manticore'
import '@webx-ui/module-catalog/style.css'
import '@webx-ui/module-catalog-manticore/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...catalog(), ...catalogManticore()],
}).mount()
```

The page shows the server (address, version, table prefix, whether it answers), each language's
table — products in it against products in the database, and whether its schema is the one the
catalogue writes now — and the queue of saved products waiting to be written. A table out of date
has «Rebuild»: the rebuild runs as a job on the queue, and the page follows its progress. It needs
`search-index.manage`; looking needs `search-index.view`.

When the server does not answer, the list of products says so over the rows: the panel answers
from the database then, which searches the words as typed.
