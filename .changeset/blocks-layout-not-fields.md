---
'@webx-ui/php': patch
---

A block type's rows, columns, cards and tabs are not fields: the «has no field» refusal lists only the fields that hold a value, and a value stored under a layout id counts as a stray one (`Schema::valueFields()`).
