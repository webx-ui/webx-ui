---
'@webx-ui/core': minor
---

Table: rows that nest

`tree` turns `WxTable` into the screen a pages or categories module wants — the tree and the data
in one pane, instead of a sidebar tree beside a list of the same records. The first column carries
the indentation and the disclosure; every other column is still a column.

- **Lazy by design.** `data` is the roots and `load(row)` fetches one level, because a catalogue of
  five thousand categories is not a payload. A row says whether it is worth a chevron with
  `has_children` — `withCount('children')` under a name of your choosing. Nothing said about
  children still gets one: a branch nobody described is worth a request to find out.
- **Dragging is the ordering.** Which third of a row the pointer is over decides the landing, and a
  row can never enter its own subtree. `node-drop` carries `parent` and `index`, which is a
  `PATCH` and nothing else.
- **Holding a row over a closed branch opens it**, fetching it where the children are not in yet,
  so a move across the tree is one drag rather than a drag, a wait and another drag. Dropping into
  a branch that was never opened fetches it first: the position a row lands at is not a guess.
- **Sorting and pagination are off in this mode**, and say so by not being drawn. Ordering rows
  would scatter the branches, and a page of a tree cuts them in half.

It runs on `useTreeNodes`, the same machinery behind [`WxTree`](/components/tree), so a drop means
the same thing in both. What it does not have is the keyboard equivalent of a drag that the tree
has; a long move wants a **Move** action and a picker.
