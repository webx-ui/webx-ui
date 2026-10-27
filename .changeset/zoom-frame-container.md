---
'@webx-ui/php': minor
---

The zoom of a picture can magnify in the whole frame it stands in.

- `<x-webx-zoom frame="container">` (or `data-zoom-frame="container"`) lays the mouse's window over the whole `.webx-zoom`, with its rounding, instead of the
  picture's own box: a portrait photo in a gallery's 4:3 is no longer magnified in a strip. The
  copy is the picture's box times the scale; where it is longer than the window it rests against
  the edges and always covers it, where it is shorter it stands in the middle on `--webx-zoom-bg`.
  Fingers and `side` are as they were; the default, `picture`, prints the same markup as before.
- `module-catalog`: `images.zoom_frame` (`WEBX_CATALOG_IMAGES_ZOOM_FRAME`), `picture` by default,
  turns it on for the product's gallery.
