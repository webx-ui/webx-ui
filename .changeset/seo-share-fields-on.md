---
'@webx-ui/php': patch
'@webx-ui/module-seo': patch
---

`webx-ui/module-seo`: the SEO card shows the share title, description and picture again by default. Open Graph is still filled in from the page; the fields are there for the page whose picture or line for the networks is not its own. `WEBX_SEO_OG_FIELDS=false` hides them, and stored values are honoured either way.
