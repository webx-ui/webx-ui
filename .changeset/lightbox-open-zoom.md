---
'@webx-ui/php': patch
---

Widgets: the lightbox's opening zoom lands where the picture stays. A picture in a frame (`ratio`) no longer ends the zoom at the top of the frame and jumps into place on the next frame: the thumbnail standing in for it gets the frame's shape before the zoom, not after it. A thumbnail of another shape than the picture — a square crop a grid picks on a phone — is no longer zoomed up and then swapped for the picture's shape: the zoom grows a plain box of the picture's shape instead.
