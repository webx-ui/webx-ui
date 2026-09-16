---
'@webx-ui/core': patch
---

`WxDialog` opens with the caret in the field marked `autofocus`. Reka handed focus to the first
tabbable thing in the panel — the × in the heading — after the native `autofocus` had already
fired, so a search or a title field asked for it and lost.
