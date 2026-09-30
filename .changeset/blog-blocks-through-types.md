---
'@webx-ui/php': patch
---

`module-blog`: the article editor now passes the `blocks` tree through `storeBlocks()` before it goes into the draft, so field types inside blocks (`wx-collection`, rich text, colours) keep their values the way they would through an agent's edit.
