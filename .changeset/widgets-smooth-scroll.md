---
'@webx-ui/php': minor
---

`webx-ui/widgets`: smooth scrolling. `data-webx-smooth-scroll` on the layout's `<html>` or `<body>`
turns a notch of the wheel into one stretched glide (Lenis, built into the widget's own file and
loaded only where the attribute stands; off by default). Duration, easing, the wheel's distance and
the way to an anchor are attributes or `--webx-smooth-scroll-*` tokens. Native scrolling with
reduced motion, on touch screens and without JavaScript; paused under a modal; what scrolls inside
the page keeps its own wheel (`data-webx-scroll-native` for the theme's own); same-page anchors
glide under the sticky header and go into the address.
