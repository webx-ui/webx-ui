---
'@webx-ui/module-blocks': patch
'@webx-ui/module-admin': patch
'@webx-ui/php': patch
---

Shared blocks in the panel on the site (F3 of `WEBX_UI_BLOCK_FRAGMENTS.md`). The page tells the panel which regions it prints, and the «Blocks» tab lists a shared block on the pages that print it and on no other, its blocks under a note that an edit reaches every page where it stands. «Make it this page's own» works from the tab too: one edit of the record's draft, the copies brought in with their styles, the first opened. The «Edit for all pages» link of the field no longer needs a router. The agent's guidelines spell out placing and detaching a shared block with `blocks_edit_content`; the guide to layout regions has a section «Shared blocks».
