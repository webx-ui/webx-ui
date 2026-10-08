---
'@webx-ui/tokens': minor
'@webx-ui/core': patch
---

`WxLightbox` stacks above dialogs: a new `--wx-z-index-lightbox` layer (1250) holds both its dimming
and the gallery. Opened from a dialog — a file picker, a gallery field — the dimming sat on the
overlay layer, under the dialog, and the dialog stood between the photo and its strip.
