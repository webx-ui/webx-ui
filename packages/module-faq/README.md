# @webx-ui/module-faq

The front end of the FAQ section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel:
questions and answers, and the categories they are filed under.

The other half is the Composer package `webx-ui/module-faq`, which owns the questions, their order,
their anchors, the FAQ block and the API. A section appears in the panel when both halves are
installed.

## Install

```bash
npm install @webx-ui/module-faq
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { faq } from '@webx-ui/module-faq'
import '@webx-ui/module-faq/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...faq()],
}).mount()
```

`faq()` answers with two sections — the questions and their categories — because the panel draws
one entry per module. The server puts both in the `faq` group. `faq({ path: '/help' })` puts them
somewhere else inside the panel.

On the server, the block type the module offers is installed once:

```bash
php artisan webx:blocks:offered --install --module=faq
```

## Questions

A list and the form of one question side by side, without pages: a FAQ has dozens of questions,
and a drag cannot cross a page boundary. The open question is in the address (`?question=12`), so a
link to it is a link to the list around it too. **New question** is a row that opens an empty form;
the question is created by its first save. On a phone the form slides over the list and draws its
own «Back».

The list has two orders, and the filter decides which one you are dragging:

- **no category chosen** — the order of the whole list;
- **a category chosen** — the order inside that category only. Every other category keeps its own.

While a search narrows the list, the grips disappear. A published question with no language that
has both a question and an answer is marked: a reader sees it nowhere.

The form is the `faq.form` screen, described in JSON on the server: the question and the answer in
every language of the site, the categories, **Published**, the anchor — read-only, with a button
that copies `#anchor` for a link — and a card for the project's own fields. Save with the button or
`Ctrl+S`; leaving with unsaved changes asks first. A question has no draft: a save is on the site.

## Categories

The panel's shared category screens with this module's words, without addresses: a category is a
button in a block's filter and a way to pick questions for a block.

## On the site

The module has no page of its own. The site's FAQ page is an ordinary page with the **FAQ** block
on it; the same block with categories chosen is «the questions about X» anywhere else. See the
[guide](https://webx-ui.github.io/webx-ui/guide/faq.html).

## Licence

MIT
