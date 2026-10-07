---
'@webx-ui/php': patch
---

`module-faq` MCP: `faq_restore` and `faq_purge`; a category name two categories share is refused with their ids; dry runs of create, update and reorder go through the real write and are rolled back, so they refuse what the write refuses (a taken address) and answer what it would; unknown arguments and unknown fields in `values` are refused rather than dropped.
