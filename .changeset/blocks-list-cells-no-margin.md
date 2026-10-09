---
'@webx-ui/php': patch
---

The offered block types that lay a list out in a grid or a row — `team`, `press-logos`, `press-outlets`, `press-articles`, `reviews`, `services`, `tariffs` and `menu` — give its cells `margin: 0`. A theme's prose spaces `li + li`, and in a grid or a flex row that lifted the first cell 4px above the rest of its row. A site that already has these types keeps its own copy: `webx:blocks:offered` never overwrites one, so the fix reaches it only through the type's editor.
