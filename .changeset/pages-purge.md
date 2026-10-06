---
'@webx-ui/module-pages': minor
'@webx-ui/php': minor
---

Pages can be deleted for good from the bin: «Delete for good» in a bin row's menu and «Empty the bin» above it; API `DELETE /pages/{id}/purge` and `DELETE /pages/bin`; MCP `pages_purge` (bin only, by id, dry run lists the pages). The branch goes node by node, so each page's addresses, former addresses, SEO card and history go with it. `HasVersions` now drops an entity's history when the entity is deleted for good.
