---
'@webx-ui/php': minor
---

widgets: a strip dragged with the mouse — `data-webx-drag-scroll` claims `dist/drag-scroll.*` (about 1 KB gzip) only where it stands; the gallery's ribbon carries it. A mouse drags the strip past a 5 px threshold with the snap off, the click that ends the drag is swallowed, and on release the nearest frame comes to the start (at once with reduced motion); a finger, a touchpad, the wheel and the keyboard scroll it natively as before. A ribbon saved before gets the attribute with `webx:blocks:import --replace`. The lightbox shows the picture's caption — the link's `data-caption`, else the `<figcaption>` of its figure — under the picture's left edge (on a phone between the picture and the arrows), hidden while the picture zooms in and out; the dialog is `aria-describedby` it; no words, no element.
