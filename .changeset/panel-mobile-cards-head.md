---
'@webx-ui/core': patch
'@webx-ui/module-admin': patch
'@webx-ui/module-inbox': patch
---

The panel on a phone, three layout fixes.

- `WxTable` in card mode puts the `card-actions` to the right of the fields: one action sits on
  the line of the title, several stack down the right edge. The strip along the top of every
  card that held a single `···` is gone, and with it a line of height per card.
- The list of forms in the inbox cuts a long name to one line with an ellipsis and carries the
  whole of it in `title`; the warning, the count and the `···` stay where they are.
- `WxScreenHead` lays the name and the actions out in one row as tall as the taller of the two,
  each centred in it: buttons no longer stand out of the head above its top edge or over the
  trail, a head without a subtitle keeps no empty line under it, and on a narrow screen the row
  of buttons stands 8 from the name above and 8 from the content below.
