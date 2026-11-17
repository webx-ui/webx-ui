---
'@webx-ui/php': patch
---

widgets: with smooth scrolling (`data-webx-smooth-scroll`) a reload stands where the page was at once, and an address with `#anchor` on the anchor, as without it. Until `load` the browser is still putting the page in place — the restored scroll, the jump to the anchor, both done again as the page grows — and Lenis now keeps out of it: the wheel scrolls natively until then, and from `load` (one frame on) and from a page back out of the cache (`pageshow`) the glide starts from where the page stands, with no target left from before. Reduced motion and touch screens scroll natively, as before.
