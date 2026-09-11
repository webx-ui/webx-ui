---
'@webx-ui/core': minor
---

New component: `WxKanban` — a board of columns cards are dragged between.

Columns carry their own cards, a card needs nothing but an `id`, and what a card looks like is the
application's business: the `card` slot hands back the card, its column and its index. A move
writes itself into the arrays the board was given — that is what makes a card land where it was
dropped — and then reports `{ card, from, to, via }`, where the indices are what a Laravel update
of `status` and `position` wants.

A column's `limit` is enforced rather than decorated: at the limit the count turns red and the
column refuses further cards, while reordering inside it still works, since that does not make it
any fuller.

The dragging is SortableJS, through `vue-draggable-plus`, and it has nothing to say to a keyboard —
so the board carries its own. Space picks a card up, the arrows move it between positions and
columns (stepping over any column that is full or frozen), space drops it and escape puts it back;
every move is read out through a live region and the focus follows the card. On a phone the columns
scroll sideways and the swipe snaps to one at a time, and a drag starts after a short press, which
is what tells it apart from a scroll.
