---
'@webx-ui/php': minor
---

The lightbox of `webx-ui/widgets`: a picture that opens over the page and pages through its group — PhotoSwipe built into the package's own `dist/lightbox.js|css`, loaded only on a page that has one. `<x-webx-lightbox :image="$picture" group="…">` takes a value of a media field (its sizes are stored at upload) or any object with `url()`, `width` and `height`; a link written by hand with `data-webx-lightbox="<group>" data-width data-height` claims the lightbox too, wherever it stands. Without JavaScript the link opens the picture. The gallery slider's slides open it: a drag moves the slider, a click opens the picture, and closing on another picture brings the slider to it. Words in ten languages, the theme's icons, its colours from the site's tokens.

`theme-default`'s kitchen sink has a Lightbox page: a grid, a gallery slider, links by hand.
