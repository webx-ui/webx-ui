---
'@webx-ui/module-pages': patch
'@webx-ui/php': patch
---

Pages: the branch a page carries is counted without the bin

`descendants_count` on a page row, and `descendants` on the agent's summary of one, were
arithmetic on the nested-set bounds — and a trashed page keeps its bounds on purpose, so both
numbers went on counting pages that were already in the bin and going nowhere. The panel says
this number out loud before a delete and before a restore: the home page of a site with one
deleted page under it offered to take six pages off the site and would have taken five.

Both now answer the branch that actually moves — the live descendants of a live page, and for a
row in the bin the branch that went down with it, which is what a restore brings back. Counted
once per list by a subquery rather than once per row.
