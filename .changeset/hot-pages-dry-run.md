---
'@webx-ui/php': patch
---

`module-pages`: the dry runs of `pages_create`, `pages_update` and `pages_delete` go through the real call inside a rolled-back transaction, so they refuse what the call refuses — a taken address, the home page. `pages_create` and `pages_update` refuse a value that is not a field of the page screen (`is_home` from `pages_get` is ignored). `pages_unpublish` refuses the home page unless `force: true`. The ancestors in `pages_get` count their children. Renaming or moving a page that was never on the site no longer leaves a redirect from an address that never answered.
