---
'@webx-ui/php': minor
---

widgets: in the block `tabs` with «Picture to the edges of the screen» a wide picture without text is no longer cut by the theme's frame on a narrow column: it stands whole at the frame's height, which the theme sets as `--b-tabs-scroll-height` (unset, the picture's own height at the page's width, as before), and scrolls sideways under a finger. Each tab gets «Point of the picture on a phone» (`focus`, 0–100, 50 by default): the point the picture opens at and returns to when its tab is chosen, smoothly unless the visitor asked for less motion. Its size is explicit, so lazy pictures load in Firefox on Android too, and `sizes` asks for a file as wide as the picture is there. The tabs widget dispatches `webx:tab` with `{ index, panel }` on each selection. Wider columns, tabs with text and blocks without the option are unchanged.
