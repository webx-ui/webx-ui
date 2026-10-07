---
'@webx-ui/php': patch
---

`module-reviews` MCP: `reviews_restore` and `reviews_purge`; dry runs of create, update and reorder go through the real write and are rolled back (a rating of 7 is refused by the dry run too); unknown arguments and fields are refused; `reviews_list`/`reviews_reorder` refuse a category name two categories share, with their ids.
