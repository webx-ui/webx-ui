# webx-ui/module-snippets

Codes of outside services as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: a tag manager, a counter, a chat, a domain check — pasted by the site's owner into the head or
the body of every page, without touching the theme.

**A snippet is arbitrary HTML and JavaScript.** Whoever can save one can run anything in the browser
of every visitor and every administrator who opens the site. That is why the section sits behind one
permission of its own, `snippets.manage`, which no role gets when the module is installed: until
somebody grants it on purpose, only a super administrator sees the section.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/widgets` (cookie consent), `webx-ui/localization`, `webx-ui/routing`
- `webx-ui/theme-default` prints the three built-in places; a layout of your own adds three lines

## Install

```bash
composer require webx-ui/module-snippets
php artisan migrate
```

## Places

Three places are always there:

| Key          | Where in theme-default's layout                   |
| ------------ | ------------------------------------------------- |
| `head`       | right after `<meta charset>` and the viewport     |
| `body_start` | right after `<body>`, before "Skip to content"    |
| `body_end`   | after `@stack('scripts')`, the layout's last line |

Your own places go into `config/webx-snippets.php` — a key and the words the panel shows for it:

```php
'places' => [
    'before-footer' => 'theme::snippets.before_footer',
],
```

and the theme or the site prints them with the same component:

```blade
<x-webx-snippets place="before-footer" />
```

A layout of your own prints the built-in places the same way:

```blade
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<x-webx-snippets place="head" />
…
<body>
<x-webx-snippets place="body_start" />
…
<x-webx-snippets place="body_end" />
</body>
```

`php artisan webx:theme:check` fails on a built-in place no view prints and on a place of the config
nobody prints; `php artisan webx:doctor` warns about the same, and about snippets left in a place
the config no longer declares — those stay in the database and are printed nowhere.

## What decides whether a snippet is printed

- **Enabled.** A new snippet is off; a save of an enabled one is on the site at once — there are no
  drafts.
- **Where it is never printed:** the panel and its API, the block preview and the block stage.
- **Environment.** Outside `webx-snippets.environments` (`production` by default) only snippets
  marked "on dev and stage too" are printed.
- **Paths.** "Only on" and "except" are paths without the language prefix, `*` for anything:
  `/`, `/blog/*`.
- **Languages.** None ticked is every language.
- **Consent.** `necessary` is printed as it is. `statistics`, `marketing` and `media` are printed
  inert in a `<template data-webx-consent>` until the visitor agrees, and the consent script of
  `webx-ui/widgets` puts them on the page — scripts in the order written, an inline one after the
  external one before it has loaded.

The list of enabled snippets is cached forever and forgotten on every save, delete and reorder.

## History

Every change is in the panel's journal — who, when, what — with the **whole code before and after**,
so an old code can be pasted back by hand. There are no versions and no rollback button.

## MCP

Six tools behind the scopes `snippets:read` and `snippets:write` — and, like the panel, behind
`snippets.manage`: `snippets_list`, `snippets_get`, `snippets_create`, `snippets_update`,
`snippets_delete`, `snippets_reorder`. Every write takes `dry_run: true`; a place the config does
not declare is refused with the list of the ones it does. The resources `snippets://catalog` (the
rules and every snippet in short) and `snippets://places` (each place, whether a view prints it,
how many snippets it holds) are what an agent reads first.

## Demo

`php artisan webx:demo --module=snippets` adds one snippet to `body_end` — a comment and a
`console.log`, waiting for the analytics category, **switched off**. A site that already has a
snippet gets none; `webx:demo --remove` takes it out.

## License

MIT
