---
'@webx-ui/php': patch
---

widgets: `@webxPicture` prints each `<source>` with `style="display: none"`. The `<picture>` is
`display: contents`, so in a flex or grid container its sources were empty items of their own,
each with the container's `gap` beside it — extra space in rows of cards and galleries.
