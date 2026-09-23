---
'@webx-ui/php': minor
---

The sitemap, and a canonical on every page.

- `webx-ui/routing`: the `Visible` contract — `isVisible()`, `scopeVisible()` and
  `visibleUpdatedAt()` — so that a handler and the sitemap ask an entity the same question.
- `webx-ui/module-seo`: `/sitemap.xml` as an index with a file per registry type
  (`/sitemap-{type}.xml`, numbered past `webx-seo.sitemap.per_file`), built from canonical rows
  of every type whose model is `Visible` and filtered by the same resolver that prints the
  `<head>`: `noindex` or a canonical pointing elsewhere keeps an address out. Named routes with no
  entity join through `SitemapRoutes::register()`. Built on the first request and cached under a
  generation that moves on every save of a registry row, a card, a rule, a visible entity or an
  `seo.*` setting, with a day's TTL for what changes without a save; `webx:seo:sitemap` builds it
  ahead. `robots.txt` gains a `Sitemap:` line unless one is written. A page with no canonical of
  its own now names itself, keeping only `?page=` of the query (`webx-seo.canonical`).
- `webx-ui/module-pages`, `webx-ui/module-blog`: `Page`, `Article`, `Rubric` and `Tag` implement
  `Visible` and their handlers answer 404 by it; the blog feed is in the sitemap.
  `Rubric::scopeVisible()` takes an optional locale now.
