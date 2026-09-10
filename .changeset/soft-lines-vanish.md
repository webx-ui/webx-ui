---
'@webx-ui/core': patch
---

The editor's content area no longer inherits a host application's decoration of
content tags. A global `h2` rule was giving every heading inside `WxRichText` a top
border and 24px of padding, and a global `table` rule was turning tables into
`display: block`, which drops the fixed column layout. Headings, tables and the
document edges are now stated explicitly, and the first and last blocks in the
document carry no outer margin.
