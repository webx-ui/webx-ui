---
'@webx-ui/php': patch
---

A product saved without a unit — from the panel, MCP, an import or a bulk run — gets `webx-catalog.default_unit` (`pcs`), and one stored without a unit reads as it on the site and in the panel. A site sets another default there, or `null` for none.
