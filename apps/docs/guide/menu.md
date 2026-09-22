# Menus

`@webx-ui/module-menu` is the section where the menus of a site are arranged, and
`webx-ui/module-menu` on the server is the trees behind it and the helper that prints them. This
page is both.

Almost nothing is written in either half, which is the point. The tree is `webx-ui/nested-set`,
the addresses are the registry of [`webx-ui/routing`](/guide/routing), the languages are
`webx-ui/localization`, the section and its permissions are `module-admin`. What a menu adds is
one thing: the question "what may I point at", which lives in the frame and not here — the menu
is only its first caller.

## Install

```bash
pnpm add @webx-ui/module-menu
composer require webx-ui/module-menu
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { menu } from '@webx-ui/module-menu'
import '@webx-ui/module-menu/style.css'

createAdmin({
  modules: [menu()],
})
```

The section appears in the Content group, after the blog, once both halves are there.
Permissions: `menu.view` opens it, `menu.manage` writes — and resets the cache, because what the
reset changes is what a visitor sees.

## Declaring a menu

Which menus exist is configuration. `webx-menu.menus` names the ones the templates of the site
ask for:

```php
// config/webx-menu.php
return [
    'menus' => [
        'header' => ['title' => 'Header', 'variants' => ['link', 'button']],
        'footer' => ['title' => 'Footer'],
    ],

    'variants' => ['link'],   // for a menu that names none of its own
];
```

A declared menu is on the panel's screen from the first day, empty, and its row in the database
appears the first time somebody saves it — no write on boot and no synchronise command to forget
to run. It can be emptied but not renamed and not deleted, because `menu('header')` in a view is
a reference to that spelling. A menu nobody declared is one an administrator made in the panel
for a template or a block they are writing, and they remove it there too.

`variants` is the list of looks this menu offers. It is a string rather than an "is this a call to
action" flag: the flag covers exactly one case, and the second request is always "and this one an
outline button". The site's own markup divides by it.

## Printing it

```blade
@foreach (menu('header') as $item)
    <a href="{{ $item->url }}" @class(['is-active' => $item->isActive()])>{{ $item->label }}</a>
@endforeach
```

`menu()` hands over data and not markup. It answers a `MenuTree` — a collection of `MenuLink` —
in the language being rendered:

```php
menu('header');             // MenuTree
menu('footer')->flat();     // the same items with the nesting taken out
menu('header')->active();   // the deepest item the visitor is standing on or inside
menu('header', locale: 'uk');
```

| On a `MenuLink` | What it is                                                                                                         |
| --------------- | ------------------------------------------------------------------------------------------------------------------ |
| `label`         | Its own label, or the name of the thing it points at                                                               |
| `url`           | The address, ready to print — `null` for an item that goes nowhere                                                 |
| `children`      | A `MenuTree`                                                                                                       |
| `isHeading`     | Draw it as the heading of the group under it                                                                       |
| `variant`       | One of the looks the menu declares                                                                                 |
| `newTab`        | Open in a new tab                                                                                                  |
| `rel`           | The attribute as it is printed, `noopener noreferrer` already in it                                                |
| `isActive()`    | The visitor is here, or somewhere below here                                                                       |
| `isCurrent()`   | The visitor is on this exact page                                                                                  |
| `attrs()`       | `href`, `target` and `rel` in one array, for a template that would rather `@foreach` than write three conditionals |

There is a component too, and it is second on purpose:

```blade
<x-webx-menu::menu name="header" />
```

A flat `<ul>` with `wx-menu__*` classes and no styling, published with
`php artisan vendor:publish --tag=webx-menu-views`. It exists so that the first day of a new site
does not begin with writing a `<ul>`, and it is meant to be thrown away on the second.

## An item points at one of three things

And it says which, in a column of its own. Guessing — "if there is no entity it must be a URL" —
costs a day less to write and a great deal more afterwards: by such a record you cannot tell a
link nobody has finished choosing from one that deliberately goes nowhere.

**An entity** is a page, an article, a rubric, a tag — anything a module registered as something
to link to. The morph pair is stored and the address is asked for on every read, so **renaming or
moving a page carries its menu item with it**, and the registry leaves a redirect behind the old
address. The item's label is optional: leave it out and the item is called what the page is
called, in every language the page is translated into.

**A path** is a string, and it is what you write for the things that are not entities — a
controller of the site, a page of a package, a partner's site. Write it without the language
prefix: `/account` is printed as `/ru/account` in Russian, by the strategy
[`webx-localization`](/guide/languages) is configured with. An address you wrote the prefix into
yourself does not get a second one, and an absolute address is left alone. The panel offers the
site's own named GET routes as suggestions, so a personal-account link is chosen rather than
typed — what is stored is still the path.

**Nothing** is a group heading or a separator: the render hands over `url === null` and the
template draws a `<span>`.

Whether an item is a heading is a flag beside the target rather than a fourth kind of it. They
are different questions — how to draw it, and where it goes — and a heading with children _and_ a
page of its own is an ordinary thing ("Services"), which one column for both would lose.

::: tip A draft is a legitimate target
A menu is usually built before the pages in it are published. The panel offers a draft, draws it
dimmed and marks it **Not on the site**; the render leaves it out until it is published. The
address registry holds an address for a draft too, so this answer comes from the module that owns
the entity and from nothing else.
:::

## Languages

One tree for every language, with the labels translated. An item points at an entity, and that
entity already has a row per language in the registry — so the same item resolves to the right
address in each of them, and a second tree would repeat the structure for the sake of the one
thing that differs.

An item with no label in the language being read, pointing at nothing named in it either, is left
out of that language rather than printed empty: a blank link in a header is worse than a missing
one. The case a second tree would have covered — something the English footer has and the Russian
one does not — is the `locales` field on the item. Empty means every language.

## The cache

**If an edit has not arrived, turn the cache off first.** `webx-menu.cache.enabled` is a switch
and the section has a **Reset** button beside every menu; between them they take ten seconds and
settle the question. It matters because "my change has not arrived" does not look like a cache —
it looks like a broken save, and that is where an hour goes before anybody thinks of this page.

```php
'cache' => ['enabled' => true, 'ttl' => 3600],
```

The rest of it is automatic and worth knowing only when it is not. What is kept is one record per
menu per language, holding the parsed tree with no highlighting in it — which item is the current
one differs on every page and is worked out after the read. Everything that changes what a menu
looks like forgets the record by name: saving, deleting or **dragging** an item, editing the menu,
a change in the address registry, and **publishing or renaming an entity an item points at** —
which changes no address at all, and without which a page just published would stay out of the
menu until something unrelated was edited. The reset is precise: publishing a page forgets the
menus that page actually stands in and leaves the others alone.

What raises no model event is not covered, and cannot be: `webx:routes:rebuild`, an import, an
`UPDATE` written by hand. That is what the TTL of an hour is for — a missed reset grows over by
itself — and what the button is for. Code of your own that writes past the models should call it:

```php
app(\WebxUi\Menu\MenuCache::class)->flush();          // every menu
app(\WebxUi\Menu\MenuCache::class)->forget('header'); // one, in every language
```

Note that `mergeConfigFrom` merges one level deep, so a published `config/webx-menu.php` naming
one key inside `cache` **replaces the section whole**. Both values are read with a default of
their own for that reason, but a site that published the file before `ttl` existed will not have
it.

## The section

Menus on the left — name, key, how many items, whether the cache is built — and the tree of the
open one on the right. Which menu is open is in the address, so "the footer" is a link somebody
can send. On a phone the right-hand pane becomes a drawer with its own **back**.

Dragging changes both the order and the parent; every level is its own list and one drag is one
`move`, so where a row ends up is where it is rather than a guess about how far sideways it was
dropped. A refusal puts the tree back instead of leaving the screen disagreeing with the database.

An item is a dialog over the tree rather than a screen of its own: what it points at, the label
with its language chip, the heading flag, the look, the new tab, `rel`, the languages, the
switch. `rel` is a set of three — `nofollow`, `sponsored`, `ugc` — because a free-text one is a
field an editor eventually writes with a typo and finds out about six months later.
`noopener noreferrer` is not in it: whoever renders a new tab adds those, and that is protection
rather than a choice.

```
GET    /api/cms/menus                            the menus, declared and saved, with counts
POST   /api/cms/menus                            a menu of your own
PATCH  /api/cms/menus/{key}                      the name, and the key of your own menu
DELETE /api/cms/menus/{key}                      your own, with its items
GET    /api/cms/menus/{key}/items                the whole tree: menus are small
POST   /api/cms/menus/{key}/items                an item · PATCH · DELETE with its branch
POST   /api/cms/menus/{key}/items/{id}/move      { parent_id, index }
POST   /api/cms/menus/{key}/cache/flush          one menu · /api/cms/menus/cache/flush for all
```

## For an agent: MCP

The section is also a set of tools. With `webx-ui/mcp` installed — it comes with this module —
they appear on the panel's one MCP server at `/api/cms/mcp`; [AI agents](/guide/agents) is how a
site switches that on.

| Tool               | What it does                                                       |
| ------------------ | ------------------------------------------------------------------ |
| `menu_list_menus`  | The menus of the site: keys, names, counts, looks, the cache       |
| `menu_get_tree`    | One menu nested, with each item's target, address and availability |
| `menu_add_link`    | An item: what it points at, what it is called, where it goes       |
| `menu_update_link` | The fields of an item; what you leave out keeps what it had        |
| `menu_move_link`   | Under another item, and how far down that level                    |
| `menu_remove_link` | An item with its branch, and it says how many that was             |

Every tool that changes something accepts `dry_run: true` and then reports what it would do
without doing it. Writing needs the `menu:write` scope; reading needs `menu:read`.

**Menus themselves are not made here.** A declared menu is a template asking for that spelling,
and a menu of somebody's own is made for a template or a block that a person is writing — there is
nothing in either for an agent to decide.

Before arranging anything an agent reads `menu://menus`: the catalogue of menus with the looks
each one offers, the kinds of thing that can be linked to, and the house rules — hang an item on
an entity rather than on a path, write a path without its language prefix, leave the label out
where the entity's own name will do.

## Config

`config/webx-menu.php`:

| Key             | Default    | What it is                                                 |
| --------------- | ---------- | ---------------------------------------------------------- |
| `menus`         | —          | The menus the templates ask for: key → title and variants  |
| `variants`      | `['link']` | The looks a menu that names none of its own offers         |
| `cache.enabled` | `true`     | The first thing to switch off when an edit has not arrived |
| `cache.ttl`     | `3600`     | The backstop for what raises no model event at all         |

## What is deferred

- **Visibility by role, and "only for signed-in visitors".** It is a column and a condition, and
  it drags the cache onto the visitor with it — which is half the point of the cache. It arrives
  with the wider conversation about permissions.
- **Icons on items.** They wait on what an icon picker waits on: an icon name is checked by
  nothing on either half, so an editor typing one would find out from an empty space.
- **Mega-menus and columns.** That is markup, not data. Three levels of nesting are already here;
  the layout is the template's.
- **"Every child of this page, automatically".** Tempting and dangerous: the menu would stop being
  what the panel shows. If it is asked for twice, it comes back.
