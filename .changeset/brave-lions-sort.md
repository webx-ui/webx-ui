---
'@webx-ui/core': patch
'@webx-ui/module-blog': minor
'@webx-ui/php': patch
---

Tags: the order is on the headings, and renaming is a form.

The two buttons over the list are gone — the name and the count sort from their own headings, in
either direction, and the address carries the order so a link lands on the list somebody meant.
The server takes a leading minus for it and keeps the bare names it had: alphabetical, and most
used first.

Renaming opens a dialog with one field. In the cell it was a name that turned into an `<input>`,
which reads as a name — nothing said it could be typed in — and it saved itself on `blur`, an
event that does not bubble, so the listener on the field's wrapper heard nothing and clicking away
lost what had been typed.

`WxActionBar` wraps its buttons. They were `flex: 0 0 auto` and stayed on one line whatever the
width: measured on a 375px screen, five of them were 815px inside a bar 359 wide, and they took
the whole page sideways with them.
