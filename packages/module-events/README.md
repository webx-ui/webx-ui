# @webx-ui/module-events

The front end of the events section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: workshops, meetings and webinars — a date and a time, a place, a price and a link to book,
a gallery and «What to expect» — and the categories they are filed under.

The other half is the Composer package `webx-ui/module-events`, which owns the events, their pages
and addresses, the `.ics` files, the `Event` markup, `events()` for templates and the API. A section
appears in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-events
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { events } from '@webx-ui/module-events'
import '@webx-ui/module-events/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...events()],
}).mount()
```

`events()` answers with two sections — the events and their categories — because the panel draws
one entry per module. The server puts both in the `events` group. `events({ path: '/calendar' })`
puts them somewhere else inside the panel.

## What is here

- **The list** — a page at a time, with tabs for when: the events to come (the default), the past
  ones, all — the past ones dimmed — and the bin. The date column is the line the site prints, with
  the exact moments in the tip. «Duplicate» is in the row menu.
- **The editor** — the described screen `events.form` with the tabs Event · Settings · SEO ·
  History, autosaved into a draft and guarded by a revision. The dates travel with their offset,
  so the same event reads the same hour in every browser; an event of days (All day) shows the
  same calendar days everywhere. «Duplicate» in the bar saves, copies and opens the copy. The
  categories and the services wait in the draft with the text and reach the site on «Publish». A
  project adds its own fields with a patch into the `project-fields` card.
- **Categories** — the panel's shared category screens (`categoryRoutes`), with
  `eventCategoriesOptions()` exported for a panel that mounts them elsewhere.

The words are English by default and come from the server in the panel's language
(`webx-events::`).

Documentation: https://webx-ui.github.io/webx-ui/guide/events.html

## License

MIT
