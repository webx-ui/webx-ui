# @webx-ui/module-services

The front end of the services section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: a catalogue of services, and the categories they are filed under.

The other half is the Composer package `webx-ui/module-services`, which owns the services, their
addresses, their order and the API. A section appears in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-services
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { services } from '@webx-ui/module-services'
import '@webx-ui/module-services/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...services()],
}).mount()
```

`services()` answers with two sections — the services and their categories — because the panel
draws one entry per module. The server puts both in the `services` group.
`services({ path: '/catalogue' })` puts them somewhere else inside the panel.

## The list

The whole catalogue on one screen, without pages: a site has dozens of services, and a drag
cannot cross a page boundary. The row shows the cover, the title with the address under it, the
categories as chips (the first one is the main one) and the state.

The list has two orders, and the filter decides which one you are dragging:

- **no category chosen** — the order of the whole list;
- **a category chosen** — the order inside that category only. Every other category keeps its own.

While a search or a state narrows the list, the grips disappear: the gaps between the rows you see
are rows you do not, and a drop into one is not an order anybody chose.

## The editor

The `services.form` screen, described in JSON on the server: **Content** (blocks, autosaved into
the draft), **Settings** (title, address, categories, lead, cover, and a card for the project's own
fields), **SEO** and **History**. The categories take effect when saved; everything else waits in
the draft until it is published. A save over somebody else's is refused with the newer version.

The categories are the panel's shared category screens with this module's words.

## Licence

MIT
