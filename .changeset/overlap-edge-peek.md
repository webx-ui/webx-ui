---
'@webx-ui/php': patch
---

widgets: the edge picture of a section that slides over the one above (`data-webx-overlap`) no longer covers a full-screen hero before anything moves. At rest — and without JavaScript, under reduced motion, in print — only its lowest `--webx-overlap-peek` (0.18 of the screen's height) stands over the section above, clipped, not resized; the edge is never taller than `--webx-overlap-edge-max` (0.6 of the screen, cut from the top, its top fading in), so on a wide screen it no longer takes half of it. While the page scrolls, the section waits below its place and comes back faster than the page (`webx-overlap--rising`, a `translate` on its own view timeline, or the script's frame without scroll-driven animations): the fog climbs over the words above as they fade. Sites change nothing.
