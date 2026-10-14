---
'@webx-ui/module-blocks': minor
'@webx-ui/php': minor
---

Shared blocks in the panel (F2 of `WEBX_UI_BLOCK_FRAGMENTS.md`). `wx-fragment` is a field now: the «Shared block» form chooses one of the site's shared blocks, says whether it is on the site, leads to its editor in a new tab and makes it the page's own — the published blocks copied in its place, the first opened. «Site regions» lists the shared blocks as a group of their own, with how many pages hold each; the editor of one says where it stands, starts its preview on the first page that holds it, and frames and scrolls to it there. Selecting a block whose first element is a stylesheet — a block holding a shared block — scrolls the page preview to what it draws rather than to the `<link>`, and a block or region further down than the first height of the frame is scrolled to after the frame grows. `webx-ui/module-blocks`: `GET /api/cms/blocks/fragments`, the shared blocks with their state, for whoever reads the block catalog.
