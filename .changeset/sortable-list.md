---
'@webx-ui/core': minor
---

SortableList: a list whose order is the point

`WxSortableList` holds a list in the order it is shown, with a heading above it and buttons at the
end of every row — a gallery, the blocks on a page, the products picked for a promotion.

- **The grip is ours.** Every row carries one, and only the grip starts a drag: something has to
  say the row can be moved, and a keyboard cannot drag anything. It takes focus, and from there
  space picks the row up, the arrows move it, space drops it and escape puts it back — the same
  splice the pointer performs, announced in a live region and with the focus following the row.
  `handle="row"` drags by the whole row instead; `handle=".my-grip"` gives it to a button of yours.
- **A heading of its own**, `title` and `extra`, the pair `WxCard` uses — so a list that had been
  living inside a card keeps the same markup with one less wrapper.
- **Buttons in a row are not handles.** Links, fields and the actions are filtered out of the
  gesture, so a bin at the end of a row stays a bin even when the whole row is draggable.
- **Lists that share a `group` pass rows between them**, and an empty one is still somewhere to
  drop: the empty message is a row of the list rather than a note under it.
