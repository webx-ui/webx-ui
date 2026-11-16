---
'@webx-ui/php': minor
---

widgets: the `gallery` grid and mosaic fetch only the first screen at once — the first row and the first three of the block that opens the page; every other picture is `loading="lazy" decoding="async"`. Their `srcset` lists the media library's copies by width (640, 1280 — one not cut yet is cut after the response) and `sizes` the share of the block a cell really takes (a grid by the columns a phone shows, one column `100vw`; the mosaic's large tile two thirds, the small ones a third). A lightbox link around a picture without an alt is named "Open picture N of M" in the page's language (`<x-webx-lightbox :index :total>`). blocks: `$block->index` — the block's place among its neighbours. media: `Thumbnails::existing()` — a cut variant's key without cutting it.
