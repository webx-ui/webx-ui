---
'@webx-ui/core': minor
---

A horizontal `WxMenu` folds what it cannot fit into a branch at the end of the bar

An admin with eight sections outgrows a header long before the window becomes a
phone. Until now the bar scrolled sideways, out of sight — and worse, its entries
shrank past their own labels and slid over one another, because an entry in a bar
was an ordinary flex item that kept `white-space: nowrap`.

Entries that do not fit now move into a branch at the end of the bar, and come
back as it widens. `overflow="scroll"` keeps the old behaviour, and
`overflow-title` names the branch.

They _move_: each entry is rendered in exactly one of the two places, so it keeps
one identity and the branch shows as the trail when the page you are on is inside
it. The bar measures itself with a `ResizeObserver`, and again once the typeface
has loaded — text in the fallback face is a few pixels narrower per label, which
is enough to leave one entry in the bar that the real face has no room for.
