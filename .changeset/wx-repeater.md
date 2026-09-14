---
'@webx-ui/core': minor
'@webx-ui/schema': minor
'@webx-ui/php': minor
---

Repeater: a field whose value is a list of records

`WxRepeater` is `WxSortableList` once every row is a form — a set of fields, repeated, in an order
that is part of the answer. Rows fold to a name taken from their own fields, and each keeps a key
of its own, so writing a field, removing the row above or dragging one elsewhere never rebuilds the
form under the caret. `WxSortableList` gained `itemLabel` for the same reason: a row has to be
called something out loud.

In a described screen it is `wx-repeater`, the one type whose model is nested: the node's children
are the fields of one item, and a `name` inside it is a key of that item. A type of its own can do
the same with `nested: true`, which hands the component the node and the render context.

On the server `RepeaterType` checks, stores and resolves items with the types their children
declare — per language where a child is localized — and a failed row says which row it was.
`FieldType::resolve` now takes the requested locale as a third argument, and `Tree::fields` stops
at a named node: a repeater's children belong to its value, not to the screen.
