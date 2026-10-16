# @webx-ui/module-snippets

The front end of the Codes section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: the codes of outside services — a tag manager, a counter, a chat — pasted into the head or
the body of every page.

The other half is the Composer package `webx-ui/module-snippets`, which owns the codes, the places,
the API, the agent tools and the `<x-webx-snippets>` component that prints them on the site. A
section appears in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-snippets
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { snippets } from '@webx-ui/module-snippets'
import '@webx-ui/module-snippets/style.css'

createAdmin({
  basePath: '/cms',
  modules: [snippets()],
}).mount()
```

`php artisan webx:module:add snippets` installs both halves and writes this into the panel's entry.
`snippets({ path: '/codes' })` puts the section somewhere else inside the panel.

## The list

Every place of `config/webx-snippets.php` — the built-in `head`, `body_start`, `body_end` first —
with its codes in the order they are printed. A code is dragged by its grip inside a place to
change the order, or into another place to move it there. The switch on a row puts the code on the
site or takes it off at once.

A row says what decides where the code works: the consent category, the paths it is limited to or
kept away from, the languages, and «Dev too» for a code printed outside production.

The list also says:

- outside production — that only the codes marked «On dev and stage too» are printed;
- beside a declared place no page has printed yet — that the theme may have lost it;
- for a place taken out of the config that still holds codes — that they are printed nowhere.
  Nothing is dropped into such a place; its codes are moved or switched off from their form.

## The form

The `snippets.form` screen, described in JSON on the server: the name, the place (the places of the
config), the code in a highlighting HTML editor, the consent category, the paths and languages, «On
dev and stage too», «Enabled», and the journal with the whole code before and after. Save with the
button or `Ctrl+S`; a save of an enabled code is on the site at once.

A code with `<html>`, `<head>` or `<body>` in it is saved, and the form says what to remove above
the editor.

## Who sees it

Only the permission `snippets.manage` opens the section, and no role gets it on install: until
somebody grants it, only a super administrator sees it. Writing a code is running JavaScript on
every page of the site.

See the [guide](https://webx-ui.github.io/webx-ui/guide/snippets.html).

## Licence

MIT
