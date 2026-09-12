---
'@webx-ui/core': minor
---

`WxImageEditor`: say what the output size is the size of, and redraw the four icons.

The size row was a lone width beside two numbers, which left the reader to work out whether they
measured the crop, the picture or the panel. It is now captioned **Output**, carries a tip saying
it is the size of the picture you will get, and has a field on each side of the `×`: type into
either and the other follows the crop's shape. A height typed in comes back as exactly that
height — the width is kept as a fraction behind the scenes, so nothing reads back a pixel out.
New labels: `outputLabel`, `outputHint`, `heightLabel`.

`rotate-left` and `rotate-right` were drawing as two crescents with the arrowhead adrift: the arc
was written between two points near enough the diameter that it could not be one sweep. Both are
now a ring with a corner for a head. `flip-horizontal` and `flip-vertical` were two identical
triangles, which read as a pair rather than as a mirroring, and as a blot at toolbar size; they
are now a shape and its reflection, one filled and one drawn.
