---
'@webx-ui/php': patch
---

widgets: the edge picture of a section that slides over the one above (`data-webx-overlap`) now runs clearly ahead of its section halfway and comes back close to it at the end: furthest ahead halfway, `--webx-overlap-edge-peak` (0.33 of the screen's height), so the fog covers the words above while the section's own heading is still low on the screen, then back on a smooth curve to `--webx-overlap-edge-rest` (0.03) ahead of it, where it stays — no wide empty band of the section's colour above its words after the transition. On the way back the edge still climbs on the screen, so nothing it covered shows again; `0` and `0` keep the edge with its section. The gap under it is still the section's colour. In Chrome the ranges are read again after a size changes once the animations have started, so the words above no longer fade before that section is pinned. Sites change nothing.
