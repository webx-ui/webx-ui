---
'@webx-ui/php': patch
---

widgets: the edge picture of a section that slides over the one above (`data-webx-overlap`) now runs ahead of its section instead of riding on it: over the same part of the scroll it gets ahead of the section, so the fog covers the words above before the section does. The gap under it is filled with the section's background, so the section above never shows through. Under smooth scrolling (`data-webx-smooth-scroll`) the transition follows the glide frame by frame, and focus coming back into the faded section jumps with the glide instead of fighting it. Sites change nothing.
