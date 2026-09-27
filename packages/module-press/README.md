# @webx-ui/module-press

The front end of the press section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: the **outlets** that wrote about a site — a magazine, a paper, a portal — and the
**articles** in them, each leading to its address or to a PDF from the library.

The other half is the Composer package `webx-ui/module-press`, which owns the outlets, their
pages on the site, the three offered blocks, `press()` for templates and the API. The section
appears in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-press
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { press } from '@webx-ui/module-press'
import '@webx-ui/module-press/style.css'

createAdmin({
  basePath: '/cms',
  modules: [press()],
}).mount()
```

`press()` is one section and one entry of the menu, without a group of its own.
`press({ path: '/media-coverage' })` puts it somewhere else inside the panel.

On the server, the block types the module offers are installed once:

```bash
php artisan webx:blocks:offered --install --module=press
```

## Outlets

A list and the form of one outlet side by side, without pages: the list is where outlets are put
in order — the order of the strip of logos and the catalogue on the site — and a drag cannot cross
a page boundary. The open outlet is in the address (`?outlet=12`). **New outlet** is a row that
opens an empty form; the outlet is created by its first save. On a phone the form slides over the
list and draws its own «Back». While a search narrows the list, the grips disappear.

A row shows the logo (or the initials), the name, a star when the outlet is in the strip of logos,
how many articles it has and the languages a reader sees it in. A published outlet seen in no
language — none of its articles has a title yet, or all of them are hidden — is marked.

The form is the `press.outlet-form` screen, described in JSON on the server, in three tabs:

- **General** — the logo, the name, the address, the website, a few words about the outlet,
  **Published**, **In the strip of logos** and a card for the project's own fields;
- **Articles** — the articles as cards in their order: the title and the excerpt in every
  language, the kind, the date and how much of it is known, the link, the PDF, and **Do not
  show**. A collapsed card reads «#2 · the title». Drag a card by its grip to reorder;
- **SEO** — the card of the outlet's page, when `module-seo` is installed.

The outlet and its articles are saved together, with the button or `Ctrl+S`; leaving with unsaved
changes asks first. A refused article opens by itself with the error under its field. An outlet has
no draft: a save is on the site.

## On the site

Every outlet has a page under a prefix (`/press/tatler-asia`); the prefix itself is a page of
`module-pages` with the catalogue block on it. The blocks — a strip of logos, the catalogue, the
latest articles — and `press()` for templates are described in the
[guide](https://webx-ui.github.io/webx-ui/guide/press.html).

## Licence

MIT
