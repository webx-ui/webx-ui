---
'@webx-ui/module-blocks': patch
'@webx-ui/php': patch
---

A widget inside a block looks in the panel as it does on the page: the block type editor's stage, a block redrawn into the page preview after a field changed, and `blocks_render` now bring the stylesheets and scripts its widgets claim (a map, opening hours). `module-blocks` dispatches `Events\BlockDrawn` for a block drawn on its own, `webx-ui/widgets` answers with `Widgets::piece()` — the gathering `Widgets::finish()` does for a page — and the render answers with `assets` (`head`, `body`), which the panel adds to the frame once each (`addAssets()`). Pages and the page and region previews are unchanged.
