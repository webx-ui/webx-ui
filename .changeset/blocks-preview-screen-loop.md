---
'@webx-ui/module-blocks': patch
'@webx-ui/php': patch
---

module-blocks, widgets, theme-default: the panel's preview of a page whose first block is a `height: screen` hero no longer grows without end. The frame is as tall as its page, so `100svh` inside it was the frame's own height: the hero grew with the frame, the page with the hero and the frame with the page. The preview (and the block's stage) now marks its `<iframe>` and the page's `<html>` with `data-webx-preview` and sets `--webx-screen-height` to the height of the device drawn (1280 → 800, 834 → 1194, 390 → 844 px); theme-default sizes the page and the first screen by it there, and the frame stops following a page that keeps growing by exactly what the frame just grew by (any block of a site's own sized to the window). Smooth scrolling and parallax stay off inside the preview. Nothing to re-import: the rule is in the theme's stylesheet, not in the hero's styles.
