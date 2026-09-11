---
'@webx-ui/core': patch
---

`WxRadio` draws its mark as one SVG, so the dot stays in the centre of the ring.

The dot used to be a CSS box centred inside another CSS box, with the ring drawn as a 1px border
between them. At a fractional device pixel ratio — Windows at 125% or 150%, which is most HiDPI
screens — that border is 1.25 or 1.5 physical pixels and gets rounded on each side independently.
The content box then sits off the centre of the border box, and the dot rides along with it.

Ring and dot are now two circles sharing one origin in one coordinate system. Nothing is laid out
between them, so nothing can round them apart: the renderer resolves both against real geometry and
antialiases them. `non-scaling-stroke` keeps the ring one pixel wide at every size, the way the
checkbox border is, rather than thinning to 0.8px on `sm` and thickening to 1.2px on `lg`.
