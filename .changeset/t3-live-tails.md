---
'@webx-ui/php': minor
'@webx-ui/core': minor
'@webx-ui/module-catalog-manticore': minor
'@webx-ui/module-audit': minor
---

The console's `webx:catalog:index --rebuild` and the panel's «Rebuild» share one lock: the second
one is refused, and the search index page holds its button back while the console rebuilds.
`WxTable` keeps its heading row in sight while the page scrolls past a long table (`stickyHeader`,
on by default). In the audit's page card, a picture that answered shows its thumbnail and opens
full size; the stand's, unchecked and broken ones keep the placeholder and are not fetched.
