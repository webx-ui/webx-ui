---
'@webx-ui/php': patch
---

`webx-ui/widgets`: the header bar no longer ends one gap wider than it starts. The gap between its columns was the grid's, so the mobile menu's trigger, hidden on a wide screen, still left its gap after the actions; now each part brings its own `margin-inline-start`, and a part that is not shown brings none.
