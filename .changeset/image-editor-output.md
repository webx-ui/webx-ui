---
'@webx-ui/core': minor
---

`WxImageEditor`: adjustments, a named output size, and four icons redrawn.

**`filters`** offers brightness, contrast, saturation and black-and-white behind one button — off
by default, so an editor asked for as a cropper stays one. The picture on screen is shown through a
CSS `filter` and the canvas is drawn under the same string, so the preview and the file are one
calculation and the picture is still sampled exactly once. The result carries both the numbers and
the string, for a server that has to arrive at the same picture from the original. Where a canvas
has never heard of `filter` — Safari before 16.4, where it fails silently and writes an unadjusted
file — the same arithmetic is done over the pixels instead.

**The output size** was a lone width beside two numbers, which left the reader to work out whether
they measured the crop, the picture or the panel. It is captioned **Output** now, with a tip saying
it is the size of the picture you will get and a field on each side of the `×`: type into either
and the other follows the crop's shape. A height typed in comes back as exactly that height — the
width is kept as a fraction behind the scenes, so nothing reads back a pixel out.

**The icons.** `rotate-left` and `rotate-right` were drawing as two crescents with the arrowhead
adrift: the arc was written between two points near enough the diameter that it could not be one
sweep. Both are a ring with a corner for a head now. `flip-horizontal` and `flip-vertical` were two
identical triangles, which read as a pair rather than as a mirroring and as a blot at toolbar size;
they are a shape and its reflection, one filled and one drawn.

New labels: `outputLabel`, `outputHint`, `widthLabel`, `heightLabel`, `adjustLabel`,
`brightnessLabel`, `contrastLabel`, `saturationLabel`, `monoLabel`.

Also: a modal's closing timer no longer reaches through `window` to clean up after itself, which
threw a reference error whenever the panel outlived the page that opened it.
