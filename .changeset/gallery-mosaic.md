---
'@webx-ui/php': minor
---

Widgets: the `gallery` block gets a third view, the mosaic (`view: mosaic`) — one large picture and two smaller ones, the large on the left and the two beside it one above the other, or the large on top and the two side by side in a narrow column; captions under the pictures. `mosaic: still` (the default) shows the first three while the lightbox pages through every picture of the gallery; `mosaic: slider` is a carousel of threes on `<x-webx-slider>` with the gallery's arrows, dots, loop and autoplay. The lightbox gives the focus back to the opener when the last picture shown has a hidden link. Grid and slider print as before; a site takes the view with `webx:blocks:import gallery --replace=gallery`.
