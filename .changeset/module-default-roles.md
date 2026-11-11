---
'@webx-ui/php': minor
---

`module-admin`: optional contract `Contracts\HasDefaultRoles` — a module names its own permissions in the ready-made roles `editor`, `seo`, `developer`. `module-auth`: `DefaultRoles` adds them to its list, so they are in the role when it is created and topped up by `webx:roles:defaults --top-up`; a permission the owner took away still stays away. The roles of the modules on the list are unchanged.
