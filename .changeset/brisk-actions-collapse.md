---
'@webx-ui/core': minor
---

`WxAction`, `WxActions`, `WxDropdown` and `WxDropdownItem` — the row of icon buttons at the end of a
record, and the panel it folds into.

An action is described by what it does: `type="remove"` is a red trash can called "Delete", and
`icon`, `tone` and `label` each override one of those when a screen needs something else. `hidden`
draws nothing but keeps the square, so a list where one record may not be deleted still lines up
with the rows where it may — the distinction `disabled` cannot make, since a greyed button says
"not now" rather than "not for this record".

`WxActions` lays the row out and, with `collapse`, measures it against its container and folds it
into a dropdown as soon as it stops fitting. The row is never unmounted, only taken out of the
flow: keeping its natural width is the only way to know when there is room for it again.

`WxDropdown` is the general case — a `trigger` slot and a content slot, nothing assumed about
either. It renders the trigger as the element you pass rather than wrapping it in a button of its
own, because the trigger is nearly always a `WxButton` or a `WxAction` and a button inside a button
is invalid HTML. A click inside closes the panel by default; a panel of filters turns that off with
`:close-on-click="false"` and dismisses itself through the `close` handed to the content slot.
`WxDropdownItem` is one row of a menu — icon, label, trailing note, and a `danger` tone for the
destructive one at the bottom.
