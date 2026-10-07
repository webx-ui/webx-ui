---
'@webx-ui/php': patch
---

`module-recipes` MCP: `recipes_restore` and `recipes_purge`; a nutrient name two nutrients share is refused with their ids; dry runs of create, update and reorder go through the real write and are rolled back, so they refuse what the write refuses (a taken address) and answer what it would; unknown arguments and unknown fields in `values` are refused rather than dropped.
