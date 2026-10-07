---
'@webx-ui/php': patch
---

`module-services` MCP: `services_restore` and `services_purge`; dry runs of create, update and reorder go through the real write and are rolled back, so they refuse what the write refuses (a taken address) and answer what it would; unknown arguments and unknown fields in `values` are refused rather than dropped.
