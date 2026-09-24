---
'@webx-ui/core': patch
---

`WxListDetail` no longer folds and unfolds without end when its width sits just over a
threshold. A record opened beside the list made the page taller, the scrollbar took its width,
and the record went into the drawer. The page got shorter, the scrollbar went away, and the record
came back. Once a layout stands, it now gives 24 px before it folds. Unfolding still needs the
full width.
