---
'@webx-ui/core': minor
---

SelectionArea: the rubber band over a grid or a list

Drag across a media library, a card grid or a list of rows and everything the box touches is
selected. `WxSelectionArea` draws the box and holds the selection; `v-wx-select="file.id"` hands it
an item.

- A directive rather than a wrapper component, because the thing being selected is already an
  element — a card, a row, a `<tr>` — and a box of ours around each one would break the grid or the
  table it sits in. It also keeps the value's type, which a `data-` attribute cannot: the selection
  comes back as the numbers the API expects. Markup that is not written in Vue can still say
  `data-wx-selectable="42"` and get the string.
- The whole gesture, not just the box: shift or ctrl adds, alt takes away, a click picks one, a
  ctrl-click toggles it, a shift-click takes the run, a click beside them clears, ctrl+A and escape
  do all and none. A selection people can only make by dragging is one they cannot make an item at
  a time.
- A drag that begins on a link, a button or a field is left to that control, so the bin on a tile
  stays a bin.
- Dragging past the edge scrolls, faster the further past it you are. The items are measured once,
  in coordinates that do not move when anything scrolls, so the scrolling costs nothing per frame
  but the arithmetic.
- Off for touch by default: on a phone a drag across a grid means scroll, and taking that away
  leaves people stranded.
