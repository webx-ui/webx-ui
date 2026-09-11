---
'@webx-ui/core': minor
---

Transfer: two lists and a pair of arrows

`WxTransfer` is the shape for choosing out of a set you also need to see — permissions, roles, the
columns of a report. `items` is everything, `v-model` is the right-hand panel, and the left is
simply what is left.

- **The right panel is in the model's order**, not the catalogue's. The model is an array and that
  order is what will be saved; a panel that showed some other order would be quietly lying about
  what is about to be sent.
- **The heading's checkbox ticks what the search left showing**, and nothing behind it. A
  select-all that quietly took forty hidden rows with it is a trap, not a convenience.
- Tick and press an arrow, or double-click a row to move that one. `disabled` on an item pins it to
  the side it is on, from either direction. Every move is announced in a live region.
- Side by side is the point of it, so when there is no room the panels stack and the arrows turn to
  point up and down — decided by the panel's width, not the window's.

Also fixes a **disabled outline button that could not be seen**: `.wx-button--outline.is-disabled`
took its border and its label from `--wx-button-bg-disabled`, which for the default type is the
surface colour — so the button was painted in the colour of whatever it was sitting on, in both
themes. It now uses the muted border and the disabled text colour. A disabled control still has to
be seen to be disabled.
