---
'@webx-ui/module-blocks': patch
'@webx-ui/php': patch
---

Block thumbnails are drawn in the site's own clothes: the panel loads the stage page once, keeps its stylesheets, fonts and the wrappers around the block's place, and drops the header, the footer and every script. The manifest names the stage (`meta.stage`) once `webx-blocks.layout` is set; without it the thumbnails stay bare.
