---
'@webx-ui/module-media': minor
'@webx-ui/core': patch
'@webx-ui/php': minor
---

A second pass over the file manager, from using it: upload refusals written in extensions rather
than a paragraph of mime types, a status bar under the grid instead of a toolbar that grows a
line, folders created through a dialog rather than a `prompt` the browser may refuse, the page in
a card, and the image editor cropping what the person actually framed.

`@webx-ui/core`: `WxFileCard` falls back to the old clipboard when the modern one refuses, so the
green tick appears wherever the copy actually worked.
