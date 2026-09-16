# @webx-ui/module-pages

The front end of the pages section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: the site's pages as a tree, their addresses, and the dialogs that create and move them.

The other half is the Composer package `webx-ui/module-pages`, which owns the tree, the
addresses and the API. A section appears in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-pages
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { pages } from '@webx-ui/module-pages'
import '@webx-ui/module-pages/style.css'

createAdmin({
  basePath: '/cms',
  modules: [pages()],
}).mount()
```

`pages({ path: '/content' })` puts the section somewhere else inside the panel.

## The list

One level of the tree at a time. The home page is pinned at the top and its children are the
level below it: every page of the site is inside the home page, so drawing it as a branch would
give every row a step of indentation that says nothing.

- **Children arrive when a branch is opened** — a catalogue of a few hundred pages is never
  fetched whole.
- **Searching puts the tree away** and answers with a flat list of matches, each with its
  address: a branch drawn for the sake of one match deep inside it tells the reader nothing.
- **The bin is a filter, not a section.** It lists the pages somebody deleted; whatever went
  down with a page comes back with it.
- **Two ways to move a page:** drag it, or use “Move…” and pick the page it goes inside —
  which is the one that works on a touch screen and in a big catalogue.

Moving a page rewrites every address under it and leaves a redirect on each of the old ones,
and the section says so out loud: an editor should not learn about a thousand redirects from a
search engine.

## The API

`createPagesApi(context)` is the same set of calls the screen makes, for a panel that wants to
do something else with them:

```ts
const api = createPagesApi(useAdmin())

await api.list({ parent: 12 })
await api.create({ title: 'About us', parent_id: 1 })
await api.move(12, 1, 'inside')
await api.remove(12) // to the bin, with the branch under it
```

## Licence

MIT
