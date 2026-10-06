---
'@webx-ui/module-admin': patch
'@webx-ui/module-pages': patch
'@webx-ui/php': patch
---

The page editor's address is the panel's shared slug field (`wx-slug`), as on a catalog product: the address of the page above stands inside the field in front of the slug, in the language being edited, and a changed slug on a page that has an address says that the old one will lead to the new one. The separate read-only «Address» line under it is gone. `GET /pages/{id}` also answers `addresses` — the page's current address by language. `RecordAddress` takes an optional `missing()`: with it, a `null` prefix means the record has no address in this language, and the field says so instead of printing `/`.
