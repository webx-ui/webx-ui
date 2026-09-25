---
'@webx-ui/module-blocks': patch
---

A component's thumbnail on the Blocks screen shows the whole component. It was drawn at a column's
width and cut to a 120 px band, so a card taller than it is wide showed only its photo; now the
root of what was drawn is measured inside the frame and scaled to fit a 180 px box both ways, in
the middle of it, clipped to the component itself. A small one — a badge — keeps its own scale
rather than being blown up. Blocks are drawn as before: a band of a page, from the top.
