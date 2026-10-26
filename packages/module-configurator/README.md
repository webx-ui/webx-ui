# @webx-ui/module-configurator

The front end of the configurators section of the [WebX UI](https://github.com/webx-ui/webx-ui)
admin panel: a product built to size — a shower enclosure, a door, a fence, a print — as steps of
options and sizes, with rules for what is shown when, an area formula and a price with its
breakdown, all edited without code and previewed live.

The other half is the Composer package `webx-ui/module-configurator`, which owns the configurators,
their drafts and versions, the check of a schema, the pricing engine the site and requests are
priced by, the API and the MCP tools. A section appears in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-configurator
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { configurators } from '@webx-ui/module-configurator'
import '@webx-ui/module-configurator/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...configurators()],
}).mount()
```

## The engine

The pricing engine is exported on its own — no Vue, no dependencies — and is the same text as the
server's (the server's is the truth; this one draws the number first):

```ts
import { price, resolve, select, validate } from '@webx-ui/module-configurator'

const problems = validate(schema, 'en') // [] when it can be published
let state = resolve(schema, {}) // the starting choice
state = select(schema, state, 'glass', 'graphite')
price(schema, state, '2026-10-26').total
```

Both engines are held to one set of JSON cases in the monorepo
(`php/packages/module-configurator/resources/fixtures/pricing/`).

## The field `wx-configurator`

`configurators()` also registers the field type `wx-configurator`: one configurator, chosen by name
and kept by its key — what a block of the site names it by.

The guide: <https://webx-ui.github.io/webx-ui/guide/configurators.html>.
