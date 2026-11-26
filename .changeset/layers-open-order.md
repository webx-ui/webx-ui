---
'@webx-ui/core': patch
---

A dialog opened from a popover is drawn over it. `WxDialog`, `WxDrawer` and `WxLightbox` — the dimming and the panel — now stand on `--wx-z-index-popover`, the one layer every floating panel is on, so what ranks two layers is the order they came into the document rather than their kind: the media library opened from a link's panel («A file» → «Choose a file») covers the panel, its dimming included, instead of having the panel over its folders and search; a select opened inside a dialog still covers the dialog, and a lightbox opened from a dialog still covers it. Escape closes the dialog alone, and the panel is still open with the chosen file in it. `--wx-z-index-dialog` and `--wx-z-index-lightbox` stay in the tokens and are no longer used by the components; toasts and tooltips stay above.
