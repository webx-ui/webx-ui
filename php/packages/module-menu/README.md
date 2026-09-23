# webx-ui/module-menu

Menus for the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel. One tree per menu, items
that point at an entity, at a path, or deliberately nowhere — and a helper that hands a template
the finished links rather than markup to override.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin` (the panel and the link contract), `webx-ui/routing` (addresses),
  `webx-ui/nested-set`, `webx-ui/localization`, `webx-ui/mcp`

## Install

```bash
composer require webx-ui/module-menu
php artisan migrate
```

Which menus the site has is a matter of configuration:

```php
// config/webx-menu.php
'menus' => [
    'header' => ['title' => 'Header', 'variants' => ['link', 'button']],
    'footer' => ['title' => 'Footer'],
],
```

A declared menu is one a template asks for by name. It shows up in the panel straight away,
empty; its row in `menus` appears the first time somebody saves it. It can be emptied, but not
deleted and not renamed — a template refers to the spelling. An administrator can also make a
menu of their own in the panel and remove it there.

## Reading a menu

```blade
@foreach (menu('header') as $item)
    <a href="{{ $item->url }}" @class(['is-active' => $item->isActive()])>{{ $item->label }}</a>
@endforeach
```

```php
menu('header');               // a MenuTree — a collection of MenuLink, nested
menu('footer')->flat();       // the same items without the nesting
menu('header', locale: 'uk'); // another language
```

A `MenuLink` has `label`, `url`, `children`, `isHeading`, `variant`, `newTab`, `rel`,
`isActive()`, `isCurrent()` and `attrs()` — `href`, `target` and `rel` in one array. `url` is
`null` for an item that goes nowhere: a group heading, a separator.

There is a component too, and it is second on purpose — every real site has its own markup:

```blade
<x-webx-menu::menu name="header" class="site-nav" />
```

```bash
php artisan vendor:publish --tag=webx-menu-views
```

## What an item points at

| Target   | What is stored            | What the render does                               |
| -------- | ------------------------- | -------------------------------------------------- |
| `entity` | a morph pair              | asks the module for the address and the name       |
| `url`    | a path or a whole address | prefixes a path with the language, leaves the rest |
| `none`   | nothing                   | `url` is `null`                                    |

An item with no label of its own is called what the entity behind it is called, so renaming a
page renames the item. An anchor is a field of its own, kept without its `#`, and works for
either kind of target.

An item is left out of the render when it is hidden, when its `locales` do not name the language
being drawn, when nothing in that language gives it a label, or when the module that owns its
entity says the site would not show it right now — a draft page keeps its address and is still a
page nobody can open.

## Highlighting

`isActive()` is true on the page itself, on anything below it, and on an item one of whose
children is active. The front page is the exception: its path is empty, so only an exact match
counts — otherwise it would be highlighted on every page of the site. `isCurrent()` is the exact
match alone.

## The cache

A built menu is kept per menu and per language, without the highlighting, which is worked out on
every request. Anything that changes what a menu looks like forgets it: saving, deleting or
**moving** an item, saving a menu, a row changing in the address registry, and publishing,
renaming or deleting any entity an item points at. The last of those is the one nothing else
covers — publishing a page changes no address at all.

```php
app(WebxUi\Menu\MenuCache::class)->flush();
```

A write that goes round the models — a mass `update`, an import, a hand-written `UPDATE` — has to
call that itself; there are no events to hear. So does anything the panel's reset button does not
cover. Beyond that there is a TTL of an hour, so a miss grows out on its own.

If an edit does not seem to arrive, switch the cache off first: that failure looks like a broken
save, not like a cache.

```php
'cache' => ['enabled' => true, 'ttl' => 3600],
```

`mergeConfigFrom` merges one level deep, so a published copy of the config that names one key
inside `cache` replaces the section. Both values are read with a default of their own.

## Languages

One tree for every language, with the labels translated: an item points at an entity, and that
entity already has a row per language in the address registry, so the same item resolves to the
right address in each. `locales` on an item is for the case a second tree would have covered —
something in the English footer that is not in the Russian one.

en, ru, uk, de, pl, fr, es, it, pt, tr. Only English, Russian and Ukrainian have been read by a
speaker.

## Licence

MIT.
