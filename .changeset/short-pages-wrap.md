---
'@webx-ui/core': patch
---

`WxPagination` wraps on a narrow screen instead of running off the edge of it.

Neither the controls nor the list of page buttons wrapped, and buttons do not shrink, so on a phone
the row overflowed its container well before the page count got interesting — taking the last pages
with it, and often the next arrow too. Both now wrap and stay aligned to the trailing edge, and the
"Per page" label no longer breaks across two lines. Nothing changes on a wide screen, where there
is no wrapping to align.
