# @webx-ui/module-tariffs

The front end of the tariffs section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: price cards — a name, a badge, a price in a currency of the site's list with a period, or
words instead of a price, the lines of what the plan includes, a description, one button and the
«recommended» mark — gathered into groups and, when the site has services, linked to them.

The other half is the Composer package `webx-ui/module-tariffs`, which owns the tariffs, their
groups and orders, the Tariffs block, `tariffs()` for templates and the API. A section appears in
the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-tariffs
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { tariffs } from '@webx-ui/module-tariffs'
import '@webx-ui/module-tariffs/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...tariffs()],
}).mount()
```

`tariffs()` is **two** sections — **Tariffs** and **Groups** — in a menu group of their own, so it
is spread into the list. `tariffs({ path: '/pricing' })` puts both somewhere else inside the panel;
the groups always sit under the tariffs (`/pricing/groups`).

On the server, the block type the module offers is installed once:

```bash
php artisan webx:blocks:offered --install --module=tariffs
```

## The tariffs

A list and the form of one tariff side by side, without pages: the list is where tariffs are put in
order, and a drag cannot cross a page boundary. The open tariff is in the address (`?tariff=7`), so
a link to it is a link to the list around it too. **New tariff** is a row that opens an empty form;
the tariff is created by its first save. On a phone the form slides over the list and draws its own
«Back».

A row shows the name, the price in one line («$750 /mo», or the words instead of a number), a star
at the recommended one and a mark when it is not published. The list narrows to a group — and then
a drag is that group's own order, the whole list keeps its own — to words, or to the bin. While a
search narrows the list, the grips disappear. The bin lists what was deleted, newest first, and
brings it back to its places.

The form is the `tariffs.form` screen, described in JSON on the server: the name and the badge,
**Recommended**, the price with its currency, period and words instead of a number, the lines of
what is included, the description, the button (a label, a link and a look from the site's list),
the groups, the services — only on a site that has them — **Published** and a card for the
project's own fields. Save with the button or `Ctrl+S`; leaving with unsaved changes asks first. A
tariff has no draft: a save is on the site.

**Groups** are the panel's shared category screens with this module's words: a title, **Shown on
the site**, the order by drag.

`priceLine()` is the one line of the list, exported for a project that draws tariffs elsewhere in
the panel.

## Words

The panel's words come from the server (`webx-tariffs::*`, ten languages). `tariffsMessages` is the
English floor the section falls back on while they load, or when a key is missing.

## License

MIT
