# webx-ui/module-snippets

Codes of outside services — a tag manager, a counter, a chat — that the site's owner pastes into
the head or the body of every page without touching the theme. A snippet is raw HTML and
JavaScript printed in a **place** of the layout, filtered by paths, languages, environment and
cookie consent. The section «Codes» of the panel and the MCP tools `snippets_*` edit it. The
layout is `webx-ui/theme-default`, the consent banner and its script `webx-ui/widgets`, the panel
and its journal `webx-ui/module-admin` — read their guides for those.

## What it owns

- **Table** `snippets_codes` (`WebxUi\Snippets\Models\Snippet`): `name`, `place`, `code` (up to
  65536 bytes), `consent` (`necessary`, `statistics`, `marketing`, `media`), `only_paths`,
  `except_paths`, `locales`, `on_stage`, `enabled`, `position`; soft deletes. No drafts, no
  versions: a save is on the site at once, and the journal keeps the whole code before and after.
- **Places**: `head`, `body_start`, `body_end` are always there; the site's own come from
  `config('webx-snippets.places')` (key → label, plain words or a translation key).
- **Blade component** `<x-webx-snippets place="…" />` (view `webx-snippets::place`); theme-default's
  layout prints the three built-in places.
- **Panel screen** `snippets.form`, nodes `content`, `name`, `place`, `code`, `settings`,
  `enabled`, `consent`, `on_stage`, `coverage`, `only_paths`, `except_paths`, `locales`,
  `history`, `journal`. API under `/api/cms/snippets`. One permission, `snippets.manage`, which
  no role gets on install — only a super administrator sees the section until it is granted.
- **MCP** tools `snippets_list`, `snippets_get`, `snippets_create`, `snippets_update`,
  `snippets_delete`, `snippets_reorder`; resources `snippets://catalog`, `snippets://places`.
  Scopes `snippets:read`, `snippets:write`; every tool also needs `snippets.manage`.
- **Checks**: a part of `webx:theme:check` (`Checks\PlacesCheck`) and the doctor check
  `Doctor\SnippetPlaces`.
- **Cache** `webx-snippets.codes` (every enabled snippet), forgotten on save, delete and reorder;
  `webx-snippets.seen` — the day each place was last printed on a page.
- Also registered: demo content (`resources/demo`) — one snippet, switched off; the table moves
  with `webx:snapshot` as content.

## Change it without forking

| You want                                     | Do this                                                                           |
| -------------------------------------------- | --------------------------------------------------------------------------------- |
| A place of your own (say, before the footer) | add it to `places` in `config/webx-snippets.php`, then print it in a view (below) |
| Other words for a built-in place             | name `head`, `body_start` or `body_end` in `places` with your label               |
| Every snippet printed on a stage copy too    | add its `APP_ENV` to `webx-snippets.environments`                                 |
| One snippet on dev and stage                 | tick «on dev and stage too» (`on_stage`) on it                                    |
| A layout of your own                         | print the three built-in places in it, as in [README.md](README.md)               |
| Other words in the panel                     | `php artisan vendor:publish --tag=webx-snippets-lang`                             |
| The config file in the site                  | `php artisan vendor:publish --tag=webx-snippets-config`                           |

A place of your own, step by step:

```php
// config/webx-snippets.php
'places' => [
    'before-footer' => 'theme::snippets.before_footer', // or plain words
],
```

```blade
{{-- in the view that should carry it, e.g. resources/views/components/layout.blade.php --}}
<x-webx-snippets place="before-footer" />
```

The key is lowercase letters, digits, `-` and `_`. The place shows up in the editor's «Place»
select and in `snippets://places` at once; `php artisan webx:theme:check` fails until some view
prints it.

## Do not

- Do not edit anything in `vendor/webx-ui/module-snippets` or copy it into the site. The rows
  above are the supported ways; if none fits, the package is missing a seam — say so.
- Do not paste a service's code into the layout or a Blade view: it would skip consent, the
  environment check and the panel's journal. Make it a snippet in a place instead.
- Do not take a place out of the config while snippets stand in it: they stay in the table and
  are printed nowhere. Move them to another place in the panel (or `snippets_update` with
  `place`) first.
- Do not set `consent` to `necessary` to make a counter or an ad pixel show up before the banner
  is answered: that is tracking without consent. Pick its real category.
- Do not grant `snippets.manage` to a content role: saving a snippet runs JavaScript in every
  visitor's and every administrator's browser. Keep it to the people who deploy code.
- Do not switch a snippet on (`enabled: true`) from MCP unless a person asked for it, and never
  paste code a person or a service did not give you.
- Do not write rows with SQL: the cache of codes is forgotten by the model's events; a raw write
  leaves the site printing the old list.

## Check your work

- `php artisan webx:theme:check` — every built-in place and every place of the config is printed
  by some view the site reads.
- `php artisan webx:doctor` — places lost, snippets in a place no longer declared, snippets waiting
  for consent on a site without the consent script.
- Open a page and view the source: the code is right after the viewport (`head`), right after
  `<body>` (`body_start`) or at the end (`body_end`); one waiting for consent is inside
  `<template data-webx-consent>` until the banner is answered. Outside production, only snippets
  with `on_stage` are there.
- With MCP: read `snippets://places` and `snippets://catalog` first; every write takes
  `dry_run: true` and answers `warnings` about `<html>`, `<head>` or `<body>` in the code.

## Read more

- [README.md](README.md) in this directory — places, what decides printing, history.
- Guide: https://webx-ui.github.io/webx-ui/guide/snippets
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_SNIPPETS.md
- Cookie consent: https://webx-ui.github.io/webx-ui/guide/widgets
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
