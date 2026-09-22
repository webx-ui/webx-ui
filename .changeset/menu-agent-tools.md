---
'@webx-ui/php': minor
---

The menus by their other doors: six tools for an agent, the catalogue to read first, and demo
content

`webx-ui/module-menu` now offers `menu_list_menus`, `menu_get_tree`, `menu_add_link`,
`menu_update_link`, `menu_move_link` and `menu_remove_link`, under `menu:read` and `menu:write`.
The names are longer than the module prefix needs and deliberately so: a real client shows a tool
by the part of its name after that prefix, and `menu_list` beside `menu_get` would stand in a
connector's settings as "List" and "Get" next to everybody else's.

They go through the doors the panel goes through. Where an item points is normalised by the same
`Link` a block field keeps and checked by the same rules the dialog is checked by — now
`Menu\Panel\ItemInput`, so there is one list rather than two that look alike — and what a position
among siblings means is `Menu\Tree\Placement`, which the drag and the tool now share. An item an
agent wrote is an item the panel would have accepted, and the cache is forgotten by the model
events either of them raises rather than by a line in a handler.

Every writing tool takes `dry_run: true`. Menus themselves are not made here at all: a declared
menu is a template asking for that spelling, and one of somebody's own is made for a template a
person is writing.

`menu://menus` is what an agent reads first — the menus with the looks each one offers, the kinds
of thing that can be linked to, and the house rules that are easy to get wrong silently: hang an
item on an entity rather than on a path, because an entity carries its address and a typed path
goes stale without saying so; write a path without its language prefix; leave the label out where
the entity's own name will do.

`webx:demo` now fills a header and a footer out of the pages it has just created — all three kinds
of target on one site, since the difference between them is the thing a screenshot cannot show.
The pages come out of the run's journal rather than out of a query, which is what `requires:
['pages']` buys. The `menus` rows are deliberately not written down: the only ones this creates
are declared, a declared menu refuses to be deleted because a template names it, and an entry
`--remove` could not undo would be worse than an empty menu left behind.
