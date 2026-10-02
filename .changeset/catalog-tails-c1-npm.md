---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-catalog': minor
'@webx-ui/module-auth': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-inbox': patch
---

`WxTable` takes `selectRowLabel` and `selectAllLabel` for its checkboxes, and every selectable list of the panel passes them translated (`filters.select-row`, `filters.select-all` in `module-admin`). The history shows a list of names as the names, comma-separated, instead of JSON. The catalogue's list of products warns when the database engine is past `sql_engine_limit`; the tree of categories shows an address without the trailing slash; the category picker of a bulk action says «Choose a category».
