---
'@webx-ui/php': patch
---

widgets: a slider with `loop` and too few slides for Swiper's loop (a gallery mosaic of two pages) goes round by `rewind` instead of stopping at the edges: both arrows stay on, "previous" from the first slide goes to the last and "next" from the last to the first, the keys and autoplay too, and the arrows are announced by where they go, not as "the first slide". Enough slides — the loop as before; one slide — no arrows.
