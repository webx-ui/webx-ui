# webx-ui/module-menu

The menus of the site: one tree per menu, keyed by the name a template asks for (`header`,
`footer`), with items that point at an entity of the site, at a path or address, or nowhere (a
heading). A template reads a menu through `menu('header')` and gets finished links, not markup.
The section «Menus» of the panel and the MCP tools `menu_*` edit it. The tree is
`webx-ui/nested-set`, an item's address comes from `webx-ui/routing`, the link contract and the
panel from `webx-ui/module-admin`, the languages from `webx-ui/localization` — read their guides
when the question is about one of those.

## What it owns

- **Tables** `menus` (`WebxUi\Menu\Models\Menu`: `key`, translatable `title`) and `menu_items`
  (`WebxUi\Menu\Models\MenuItem`: one nested set scoped by `menu_id`; `target` is `entity`, `url`
  or `none`; translatable `title`, `hash`, `variant`, `is_heading`, `new_tab`, `rel`, `locales`,
  `visible`). One tree serves every language; `locales` limits an item to some of them.
- **Config** `config/webx-menu.php`: `menus` (the declared menus and their `variants`), the
  fallback `variants`, `cache.enabled`, `cache.ttl`. A declared menu shows in the panel at once,
  empty; its `menus` row appears on the first save.
- **Render** the helper `menu($key, locale: ...)` → `WebxUi\Menu\Rendering\MenuTree` of
  `MenuLink` (`label`, `url`, `children`, `isActive()`, `isCurrent()`, `attrs()`); the component
  `<x-webx-menu::menu name="header" />` with views `webx-menu::menu` and `webx-menu::item`.
- **Cache** `WebxUi\Menu\MenuCache`, per menu and language; saves, moves, deletes, address
  changes and publishing an entity an item points at forget it on their own.
- **Panel section** `menu` (order 400, requires `pages`); API under `config('webx-admin.api_path')` + `/menus`, a menu
  addressed by its key, with `cache/flush` for one menu or all; permissions `menu.view`,
  `menu.manage` (the cache reset is `manage`).
- **MCP** tools `menu_list_menus`, `menu_get_tree`, `menu_add_link`, `menu_update_link`,
  `menu_move_link`, `menu_remove_link`; resource `menu://menus` (the catalogue and house rules);
  no prompt. Scopes `menu:read`, `menu:write`. Menus themselves are not made through MCP.
- Also registered: audit checks `menu.broken` and `menu.redirect` when `webx-ui/module-audit` is
  installed, a block type `menu` offered to `webx-ui/module-blocks`, demo content
  (`resources/demo`).

## Change it without forking

| You want                               | Do this                                                                                              |
| -------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| A menu a template asks for             | add it under `menus` in `config/webx-menu.php` (`php artisan vendor:publish --tag=webx-menu-config`) |
| Item looks (a button, an outline link) | `'variants' => ['link', 'button']` on that menu; your markup divides by `$item->variant`             |
| Your own markup for a menu             | `@foreach (menu('header') as $item)` in the layout, using `attrs()` and `isActive()`                 |
| Change the component's markup          | `php artisan vendor:publish --tag=webx-menu-views`, keep only the files you change                   |
| A menu inside a site built from blocks | `php artisan webx:blocks:offered --install --module=menu`, then edit the type in the panel           |
| Other words in the panel               | `php artisan vendor:publish --tag=webx-menu-lang`                                                    |
| A cache that lives shorter, or none    | `'cache' => ['enabled' => ..., 'ttl' => ...]` in the published config                                |
| Forget menus after a bulk write        | `app(WebxUi\Menu\MenuCache::class)->flush()`, or `forget('header')` for one menu                     |

A published config replaces the whole `cache` section if it names one key inside it
(`mergeConfigFrom` merges one level deep); both values have a default of their own when read.

## Do not

- Do not edit anything in `vendor/webx-ui/module-menu`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not rename or remove a declared menu's key while a template still calls `menu('<key>')`: an
  unknown key renders as an empty menu, silently. Add the new key, move the template, then drop
  the old one. Declared menus cannot be renamed or deleted in the panel for the same reason.
- Do not point an item at a typed path when the target is a page or another entity: a path does
  not follow a renamed or moved page, an entity does, and its label follows the entity's name
  when the item has none. Use `target: entity`; keep `url` for addresses outside the site.
- Do not write a path with its language prefix: the render adds it. Write `/account`, not
  `/en/account`.
- Do not give an item a label only to copy the page's title: an empty label is what keeps it in
  step with the page in every language.
- Do not write `menu_items` with SQL, mass `update` or an import without flushing the cache:
  those raise no model events, so the site keeps the old menu for up to `cache.ttl`. Call
  `MenuCache::flush()` afterwards, or use the panel and the MCP tools.
- Do not declare a function `menu()` in the site: the helper is guarded and quietly steps aside,
  and every `menu('header')` then calls yours.

## Check your work

- Open a page that prints the menu. A change that "did not arrive" is usually the cache: set
  `cache.enabled` to false or flush it before looking for a broken save.
- An item pointing at a draft is left out of the site but stays in the panel; `menu_get_tree`
  marks it `available: false`.
- With MCP: read `menu://menus`, then `menu_list_menus` and `menu_get_tree`; every tool that
  changes something takes `dry_run: true` first.
- With the site audit installed, `menu.broken` and `menu.redirect` list items that lead to an
  error or a redirect.

## Read more

- [README.md](README.md) in this directory — the PHP API: reading a menu, targets, highlighting, the cache.
- Guide: https://webx-ui.github.io/webx-ui/guide/menu
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_MENU.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
