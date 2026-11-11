---
'@webx-ui/php': minor
---

Widgets: tabs can cross-fade. `<x-webx-tabs transition="fade">` (`data-webx-tabs-transition="fade"`) keeps every panel in one grid cell, so the tabs are as tall as the tallest panel and pictures of different proportions do not make the page jump; an unselected panel is `is-hidden` (`visibility: hidden`) rather than `hidden`, roles and keys are the same, and under reduced motion the change is instant. The `tabs` block has a «Change of tab» field — at once (the default, and what a block saved before reads) or fade; a tab with only a picture shows it across the whole width.
