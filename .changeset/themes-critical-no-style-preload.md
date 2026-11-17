---
'@webx-ui/php': patch
---

themes: with critical CSS the deferred stylesheets of the head are no longer preloaded — no `<link rel="preload" as="style">` for them, and the one `@vite` prints is dropped. A preload fetched them at the highest priority, against the document, the fonts and the first screen's picture; a `media="print"` sheet is fetched at the lowest. Without the critical file the page is unchanged.
