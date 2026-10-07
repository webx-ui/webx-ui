---
'@webx-ui/php': patch
---

`module-inbox`: `panel.reorder` in all ten languages, for the drag handles of the panel's lists. MCP `inbox_list` rows carry the status as its key, and the answer names every key once under `statuses` (title, `is_closed`, `is_spam`) instead of repeating the whole status, every language of its title included, on each row.
