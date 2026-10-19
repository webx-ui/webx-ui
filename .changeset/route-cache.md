---
'@webx-ui/php': patch
---

`webx-ui/routing`: a list of cards no longer asks the registry once per link. `url()` reads
`RouteCache` — the request's memory, then one `Cache::many()` of the site's cache (on Redis one
`MGET`), then one query a type — on keys that move with the `routes` cache group, so a renamed
or rebuilt address is seen at once on any cache store. `RecordQuery` preloads the addresses of its
records and their relations; `webx:routes:rebuild` moves every cached address.
`webx-ui/module-blog`: the block "Latest articles" draws the articles it resolved instead of
asking the feed for them a second time.
