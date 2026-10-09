---
'@webx-ui/php': minor
---

`webx-ui/widgets`: `<x-webx-mobile-menu>` (three zones in a modal dialog, 100dvh, closes on
navigation, on Back and on a swipe; `<x-webx-mobile-menu.nav>` in `accordion` or `drill` mode) and
`<x-webx-header>` (folds its navigation into the mobile menu when the items stop fitting, at a
width or never; sticky, hide-on-scroll, overlay; `--webx-header-height` kept live for anchors;
`<x-webx-header.nav>` with dropdowns and mega panels), both in the shared runtime.
`theme-default` draws its header with them. With a theme, the blocks demo leaves the header and
footer regions to it, and the menu demo nests the pages it links under their parents in the
header.
