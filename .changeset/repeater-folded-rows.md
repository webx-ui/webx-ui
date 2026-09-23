---
'@webx-ui/core': minor
'@webx-ui/schema': minor
'@webx-ui/php': patch
---

A list of records reads as a list: folded rows named `#1 · …`.

`@webx-ui/core`: `WxRepeater` names a row by its position and then its `itemLabel` — `#2 · Lviv`,
`#2` without one — and cuts a long header with an ellipsis. A key that holds a translated field
shows the language being edited, else whichever is filled in; before, such a map made the header
fall back to a bare number. `dragLabel` joins `addLabel` and `removeLabel`, so the grip's name can
be translated too.

`@webx-ui/schema`: `wx-repeater` on a screen starts folded unless the node sets
`collapsed: false`, and takes its words — add, remove, reorder, the empty text — from the panel's
dictionary; a node's own `addLabel` and the rest still win.

`webx-ui/module-admin`: `screens.repeater.*` in all ten languages. `webx-ui/module-blocks`: the
guide for agents names the repeater's props.

A row lines its grip, header and actions up on one centre, and its fields run under the actions —
and, in a repeater narrower than 560px, under the grip as well, so a phone gives the fields the
whole width.

`webx-ui/module-blocks`: `wx-row` and `wx-col` inside a `wx-repeater` of a block's schema no longer
hide the fields in them. The bridge that names a block's nodes by their `id` named the layout too,
and a named node is a field to the walk — so an image in a column was never resolved on the site.
Layout keeps its id and gets no name; `Schema::LAYOUT` is the one list of those types.
