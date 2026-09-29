---
'@webx-ui/php': patch
---

`webx:demo` gives the catalogue's categories their addresses. The panel's form fills a category's slug from its name, the model does not, so the demo made fifteen categories with no slug and no page on the site; it now names each one, with `-shop` added when a page or a reserved path already holds the word.
