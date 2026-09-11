---
'@webx-ui/core': patch
---

Two fixes the inbox screen turned up, both about height.

`WxMain` is a flex column now, and its inner element stretches. As a block it was only as tall as
its content, so a screen asked to fill the page — a `WxListDetail` under a `<router-view />` —
measured its `height: 100%` against that instead of against the column, and stopped halfway down
the window. A scrolling column keeps the old arrangement, because there the inner element has to be
as tall as its content or the bottom padding never makes it into the scroll — 1288px of content in
a 216px column scrolls 1312px, not 1300.

`WxAside scroll` is capped with `max-height: 100dvh` rather than fixed at `height: 100dvh`. Under a
header, in a shell that fills the screen, the column is already the height of its row, and a hard
viewport height there is a header taller than the window: the layout overflowed by exactly the
header, every time. The cap still does its job on a page that scrolls, which is what the viewport
height was there for.
