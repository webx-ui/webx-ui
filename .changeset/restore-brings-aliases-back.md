---
'@webx-ui/php': patch
'@webx-ui/module-pages': patch
---

Restoring from the bin brings back the old addresses too. Into the bin, an entity's aliases are
kept aside in `routes_trashed` (a new migration in `webx-ui/routing`) instead of being lost; a
restore puts back each one nobody took meanwhile, and a force delete removes them. A page restore
answers `aliases_restored` and `aliases_dropped`, and the panel warns about the dropped ones.
