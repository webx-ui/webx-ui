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

A column is more than a heading over a list. The `column-actions` slot is the end of that heading —
a plus, a menu — and sits outside everything that drags, so pressing it never starts a move.
`collapsible` folds a column down to a strip with its name read the long way, remembered through
`v-model:collapsed`; folded, it holds nothing reachable, so it takes no cards and a keyboard move
passes it by. `column-addable` puts a column-shaped button after the last column, and
`reorder-columns` lets the columns themselves be dragged by their headings — by the heading only,
so a card is still picked up by the card.

The dragging is SortableJS, through `vue-draggable-plus`, and it has nothing to say to a keyboard —
so the board carries its own. Space picks a card up, the arrows move it between positions and
columns (stepping over any column that is full or frozen), space drops it and escape puts it back;
every move is read out through a live region and the focus follows the card. On a phone the columns
scroll sideways and the swipe snaps to one at a time, and a drag starts after a short press, which
is what tells it apart from a scroll.
