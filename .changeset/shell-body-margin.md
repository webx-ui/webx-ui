---
'@webx-ui/module-admin': patch
---

A dialog no longer steps the page sideways

The shell resets the browser's margin on `<body>`, but it did so off `#webx-app` — the mount
point the Blade shell renders — so a panel mounted anywhere else kept the eight pixels. What
that cost was not the gap around the frame. Every dialog locks the page, and the lock zeroes
`margin-right` and pays the scrollbar back as padding, so a body that had a margin got that
margin's width back as content: the whole panel widened when a dialog opened and snapped back
when it closed.

The reset now hangs off `data-wx-shell`, the attribute the shell already writes on the document
root, so it holds wherever the panel is mounted.
