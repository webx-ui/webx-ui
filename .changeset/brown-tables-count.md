---
'@webx-ui/core': minor
---

`WxTable` and `WxPagination`, the pair that renders a paginated list.

`WxTable` takes a Laravel `->paginate()` payload as it arrives — or a plain array — and renders
columns described in an object: a dotted `key` reads through an eager-loaded relation, `formatter`
turns the value into text, and `cell-<key>` replaces the cell outright. Sorting cycles ascending,
descending and off, and is reported rather than applied: the rows on screen are one page out of an
ordered query, so reordering them here would shuffle the page and look right while being wrong.
Selection holds row keys rather than rows, so it survives paging away and back, and the header
checkbox works on the current page without disturbing keys picked elsewhere. Loading dims the table
instead of emptying it, and the empty state waits for the load to finish.

`WxPagination` reads the page, the size and the totals straight out of the paginator, keeps the
first and last pages reachable, and spells out a gap of a single page rather than hiding it behind
an ellipsis.

Both state their own `display`, `overflow`, `margin`, `border` and row background rather than
inheriting them, so a host stylesheet that restyles bare `table` and `tr` elements cannot take the
layout away from the table or the stickiness away from its header.
