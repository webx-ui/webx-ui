---
'@webx-ui/php': minor
---

`webx-ui/module-menu`: the menus of a site, and the helper that prints them

One tree per menu, scoped by `menu_id`, with items that point at an entity, at a path, or
deliberately nowhere — said out loud in a `target` column rather than guessed from which other
column happens to be empty. A group heading is a flag of its own beside it, because how to draw an
item and where it goes are two questions, and a heading with children and a page of its own is an
ordinary thing.

Which menus exist is configuration: `webx-menu.menus` names the ones the templates ask for, and the
row in `menus` appears the first time one is saved — no write on boot, no synchronise command. A
declared menu can be emptied but not deleted and not renamed, because a template refers to its
spelling; an administrator makes and removes their own.

Outwards it hands over data rather than markup:

```blade
@foreach (menu('header') as $item)
    <a href="{{ $item->url }}" @class(['is-active' => $item->isActive()])>{{ $item->label }}</a>
@endforeach
```

A `MenuLink` carries the label, the address, the children, the flags and `attrs()`. There is a
`<x-webx-menu::menu>` too, and it is second on purpose: a component that has to be overridden is
worse than a collection somebody writes ten lines against, and `vendor:publish
--tag=webx-menu-views` is how it stops being used.

One tree for every language, with the labels translated — an item points at an entity, and that
entity already has a row per language in the registry, so the same item resolves to the right
address in each. An item nobody has translated is left out of that language rather than printed
empty, and `locales` covers the case a second tree would have: something in the English footer that
is not in the Russian one.

Highlighting is worked out after the cache, on every request, and the front page is the exception it
has to be: its path is empty, which is a prefix of every address on the site.

The cache is per menu and per language, forgotten by name. Everything that changes what a menu looks
like forgets it, including the two things that are easy to miss: **moving** an item, which rewrites
bounds in bulk and never raises `updated`, and publishing an entity, which changes no address at all
and would otherwise keep a page out of the menu until something unrelated was edited. Beyond that a
TTL of an hour and a switch, because "my edit has not arrived" looks like a broken save rather than
like a cache.

Two or three queries per menu whatever its size: the items in one ordered walk, then one `resolve()`
per kind of entity in it.
