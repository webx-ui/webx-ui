---
'@webx-ui/core': patch
---

A `flush` table is flush on every side, in both of its shapes. The head kept the cells' own step
and the column of cards kept its own above and below, so inside a card the air was 16 at the sides
and 28 over the search, and the last card stood twice as far from the edge as the first stood from
the field. Both are the box's now, and what is left between the head and the rows is the panel's
step.

An empty title is no longer drawn at all. It was still a flex item, so every table without one
carried a 12px row gap above its search — and in card mode, where the tools take a line of their
own, that gap was the whole of the space above the field.
