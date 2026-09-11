---
'@webx-ui/core': minor
---

`WxContainer` gains `viewport`, the shape an admin shell actually has

`full-height` is `min-height: 100dvh`: the container may grow past the window, and
so nothing inside it ever overflows. That made `scroll` on `WxMain` a no-op — the
column grew with its content and the page was simply cut off at the bottom of the
shell, with no scrollbar anywhere.

`viewport` caps the shell at the window instead, so the column inside it overflows
and scrolls. `full-height` still means what it said, for a document whose page
scrolls as a whole.
