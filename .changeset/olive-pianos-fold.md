---
'@webx-ui/module-admin': minor
'@webx-ui/module-blog': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-inbox': patch
'@webx-ui/module-seo': patch
'@webx-ui/module-auth': patch
---

`rowMenuWidth` says how wide a column holding a `···` has to be, and every list reads it. The menu
is a finger target — 44px under `(pointer: coarse)` — and the cell keeps 16 on either side of it,
so the 56 the sections declared was never enough: the button painted outside its column, which
nothing said out loud until cells began to clip what does not fit.

On the tags screen the selection bar keeps the one button it exists for and puts the other three
behind the same `···` a row has. The × that cleared the selection is gone: a button whose whole
job is to undo something harmless, standing beside a red "Delete", read as a way to close the bar.
