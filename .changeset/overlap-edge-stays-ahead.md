---
'@webx-ui/php': patch
---

widgets: the edge picture of a section that slides over the one above (`data-webx-overlap`) now runs clearly ahead of its section and stays ahead: it starts `--webx-overlap-edge-speed` (now 1.5) times as fast as the section, slows down and ends `lead` ahead instead of coming back to it, so the fog covers the words above while the section's own heading is still low on the screen (a quarter of the screen apart halfway on a full-screen hero). The gap under it is still the section's colour. In Chrome the ranges are read again after a size changes once the animations have started, so the words above no longer fade before that section is pinned. Sites change nothing.
