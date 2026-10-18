---
'@webx-ui/php': patch
---

`webx-ui/module-auth`: only a super administrator makes, unmakes, edits or deletes a super administrator, and only a super administrator issues a key that acts as one. Until now `admins.manage` was one request away from every permission there is — `is_super: true` on your own account, a new address and password on the owner's, or an `mcp:use` key in the owner's name. Each answers 403 «Only a super administrator can do that.» (`webx-auth::errors.only-super`, all ten languages).

`webx-ui/module-catalog`: exporting the «Deleted» list (`selection.trashed` in `POST exchange/export`, `deleted` in the MCP `catalog_export`) needs `catalog.delete`, like the list itself; without it the export is refused with 422 on `selection`.
