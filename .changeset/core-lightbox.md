---
'@webx-ui/core': minor
'@webx-ui/tokens': minor
---

`WxLightbox`: a gallery over the whole screen — arrows, ←/→/Home/End/Esc, a counter, a strip of thumbnails, swipe and swipe-down-to-close on a phone, zoom (double click or tap, wheel, pinch, +/−) and pan, a caption, "Open the original", and videos behind their posters (YouTube from `youtube-nocookie.com`, MP4 and WebM in the browser's player). It opens from a `WxImageGroup` of `<wx-image preview>`, from one picture with `preview-list` (and `preview-start`), and from code with `openLightbox(items, start)`. Every word is a prop with an English default.

`WxImage`'s `preview` now opens the lightbox instead of a dialog: the same prop, full screen, with zoom.

New icons `play`, `zoom-in`, `zoom-out`. New token `--wx-bg-lightbox` — the gallery's dimming, nearly opaque and dark in both themes.
