---
'@webx-ui/php': minor
---

Widgets: the lightbox opens a picture in a frame of a given shape — `ratio="4:3"` on `<x-webx-lightbox>`, `data-webx-lightbox-ratio` on a link, "Frame over the page" (as is / 4:3 / 16:9 / 1:1) in the `gallery` block — whole and undistorted inside it. Closing leaves the page scrolled where it stood, whichever picture of the group it closed on. The backdrop's opacity comes from `--webx-lightbox-backdrop-opacity` (still 1) or `opacity` / `data-webx-lightbox-opacity`. Without the new options everything is as before.
