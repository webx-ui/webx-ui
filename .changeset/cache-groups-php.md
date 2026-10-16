---
'@webx-ui/php': minor
---

`module-admin`: cache on versioned keys — `WebxUi\Admin\Cache\CacheGroups` (`declare()`, `bumpOn()`, `bump()`, `onReset()`, `remember()`, `key()`), one version a group in the cache, moved by the models' events or by code, after the commit inside a transaction; works on `file` and `database` as well as Redis. «System → Cache» in the panel (`cache.view`, `cache.reset`; a super administrator's until a role is granted one), `webx:cache:reset [--group=]`, the MCP tool `cache_reset` with `dry_run`, every reset a row of the journal (type `cache`). `webx:doctor` reports the cache store; `webx:cache:prune` sweeps the expired rows of the `database` store hourly. `settings`, `menu`, `blocks`, `regions`, `seo`, `sitemap`, `snippets`, `banners` and `catalog` declare their groups; `routing` tells the `routes` group about its bulk writes. The frame now registers a history type of its own, so the `history` module and its MCP tools are present on every site.
