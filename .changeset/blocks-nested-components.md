---
'@webx-ui/php': patch
---

A block type whose template has a component inside another component — `<x-webx-slider><x-webx-slide>` — publishes and renders: the inner component no longer flushes the outer one's slot data ("Undefined array key 0").
