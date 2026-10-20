# Codes of outside services

`@webx-ui/module-snippets` is the **Codes** section of the panel, and `webx-ui/module-snippets` on
the server is what it edits and what prints it. A code is whatever an outside service asks to put
on every page — a tag manager, a counter, an online chat, a site-ownership `<meta>` — pasted by the
owner of the site, without a developer and without touching the theme.

A code is raw HTML and JavaScript, printed **as is**. Writing one is running JavaScript on every
page for every visitor, so the section is behind its own permission and nobody gets it by default
(see [Who may write a code](#who-may-write-a-code)).

## Install

```bash
php artisan webx:module:add snippets
php artisan migrate
```

The first command installs both halves — `composer require webx-ui/module-snippets`, the npm package
written into the panel's entry by `webx:panel --sync`, and the build. By hand it is:

```bash
pnpm add @webx-ui/module-snippets
composer require webx-ui/module-snippets
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { snippets } from '@webx-ui/module-snippets'
import '@webx-ui/module-snippets/style.css'

createAdmin({
  modules: [snippets()],
})
```

The section is under **System**. `snippets({ path: '/codes' })` moves it inside the panel.

## A code

| Field                | What it is                                                                                    |
| -------------------- | --------------------------------------------------------------------------------------------- |
| Name                 | For administrators only; the site never shows it.                                             |
| Place                | Where on the page it is printed — one of the [places](#places).                               |
| Code                 | What the service gave you, up to 64 KB, in an HTML editor with highlighting and suggestions.  |
| Cookie consent       | `necessary` is printed at once; the others wait for the visitor's [consent](#cookie-consent). |
| Only on / Except     | Path masks without the language prefix: `/`, `/blog/*`. Empty «Only on» is the whole site.    |
| Languages            | The languages of the site the code is printed in; none ticked is every language.              |
| On dev and stage too | Off by default: outside production the code is [not printed](#environments).                  |
| Enabled              | A new code is off. A save of an enabled code is on the site at once — there is no draft.      |

The journal under the form keeps the **whole code** before and after every save, with who saved it —
from the panel or through an agent. There is no rollback button: an old version is copied from the
journal.

A code with `<html>`, `<head>` or `<body>` in it is saved, and the form says above the editor what
to take out: the page already has those tags, and a service only means what goes inside them.

## Places

Three places are always there, printed by the layout of `theme-default`:

| Key          | Where                                                                   |
| ------------ | ----------------------------------------------------------------------- |
| `head`       | As high in `<head>` as possible — right after `charset` and `viewport`. |
| `body_start` | Right after `<body>` — where a tag manager's `<noscript>` goes.         |
| `body_end`   | At the end of `<body>`, before the scripts of the site's widgets.       |

Inside a place, codes are printed in the order they are dragged into in the list.

### A place of your own

A theme or a site declares more places in `config/webx-snippets.php` — a key and its words, plain or
a translation key:

```php
// config/webx-snippets.php
return [
    'places' => [
        'before-footer' => 'theme::snippets.before_footer',
    ],
];
```

and prints it where it belongs, with the same component the built-in places use:

```blade
<x-webx-snippets place="before-footer" />
```

The **Place** select in the form offers exactly the places of the config; there is no screen to
manage places. The three built-in ones cannot be removed or renamed — naming one in the config only
changes its words.

A site with a layout of its own adds the three built-in places to it itself:

```blade
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-webx-snippets place="head" />
    …
</head>
<body>
    <x-webx-snippets place="body_start" />
    …
    <x-webx-snippets place="body_end" />
</body>
```

### Lost and undeclared places

- **Declared, but not printed by the theme** — `php artisan webx:theme:check` and `webx:doctor`
  warn about it, and in the panel the place says «Not printed on any page yet» until a page of the
  site prints it.
- **Taken out of the config, with codes still in it** — the codes are kept and printed nowhere. The
  list shows the place last with a warning, nothing can be dragged into it, and a code there can be
  moved to another place or switched off, but not switched on. `webx:doctor` says so too.

## Cookie consent

A code in `necessary` is printed as it is. A code in **Analytics** (`statistics`), **Marketing** or
**Media** goes through the consent of [site widgets](/guide/widgets): until the visitor agrees to
that category it is an inert `<template>` — nothing in it runs or loads — and the consent banner
asks for the category because the page has something that needs it. Agreeing unpacks the code
without a reload; withdrawing reloads the page.

Things to know about a code that waits for consent:

- its scripts run in the order they are written, an inline script after the external one before it
  has loaded — the usual shape of a chat widget works;
- `document.write` does not work in it: the page has finished loading by the time it is unpacked;
- a `<noscript>` part never shows — without JavaScript there is no banner and no consent;
- on a site whose layout has no `@webxTheme` there is no consent script, so such a code is never
  unpacked; `webx:doctor` warns about it.

## Environments

Every code is printed only where `APP_ENV` is one of `webx-snippets.environments` —
`['production']` by default. Anywhere else only the codes marked **On dev and stage too** are
printed: a counter on a copy of the site would count visits that never happened on the real one.
The list in the panel says so at the top when the site is not in production, and a code switched on
without that mark carries a grey **Not printed on this copy** — in its row and beside **Enabled** in
its form. In production there is no such mark.

Codes are never printed in the panel (`/cms` and its API), in block previews and on the block stage,
on the panel's error pages, or anywhere outside an HTTP request — mail, the queue, a block rendered
for an agent.

## Who may write a code

The section has one permission, `snippets.manage`: reading a code is reading the keys of somebody's
services, and writing one is running JavaScript on the site in the site's name, so there is no
harmless half to give out. No role gets it when the module is installed — until somebody grants it
on purpose, only a super administrator sees the section.

## Agents

The same section is open to [AI agents](/guide/agents) through six tools — `snippets_list`,
`snippets_get`, `snippets_create`, `snippets_update`, `snippets_delete`, `snippets_reorder` —
behind the scopes `snippets:read` and `snippets:write` and the same permission. The writing ones
take `dry_run`, and their description tells the agent plainly that writing a code is deploying
code. A new code is created switched off unless the person asked otherwise.

Two resources come first: `snippets://catalog` — the rules and the codes that already exist, so a
second counter of the same service is not added — and `snippets://places` — the places of the
site, whether the theme prints them, and the ones nobody declares any more.
