---
'@webx-ui/php': patch
---

Saves made in the panel are journalled as `panel` with the signed-in administrator in every
module, not only in `module-admin`. The frame registers a `webx.panel` middleware group (`web`,
`webx.panel-locale`, `cms.auth`, `webx.history`), and every module's panel API now sits behind it
instead of a hand-written list that left out `webx.history` — its rows said `api` and named nobody.
`module-auth`'s signed-in routes carry `webx.history` too.
