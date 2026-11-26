---
'@webx-ui/php': patch
---

widgets: the photos of a card (`<x-webx-photos>`), the overlap edge and the `gallery` ribbon print
the library's pictures by width instead of the originals. Every photo of `<x-webx-photos>` is a
`@webxPicture` (copies by width, a `<source>` per format the site cuts) with a new `sizes` prop —
the `cards` block passes the card's share of the row; the photos past the first keep their sources
held back until shown too. The overlap edge offers both files by width and in the site's formats,
`sizes="100vw"`, `fetchpriority="low"`. Every frame of the ribbon is lazy and chosen for its share
of the strip.
