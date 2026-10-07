---
'@webx-ui/php': patch
---

`module-press` MCP: `press_restore` and `press_purge`; dry runs of create, update, reorder and the article tools go through the real save and are rolled back, so a row the form refuses is refused by the dry run too; a second outlet by a name another already has (any language, any case) is refused; unknown arguments, unknown fields in `values` and in an article are refused rather than dropped.
