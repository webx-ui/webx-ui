---
'@webx-ui/module-admin': minor
'@webx-ui/php': patch
---

`createAdmin({ methodOverride })` and `createHttp({ methodOverride })`: for a host whose web server lets through nothing but GET and POST, PUT, PATCH and DELETE go out as POST with `X-HTTP-Method-Override` (`true` / `'header'`) or a `_method` field (`'field'`). Off by default; the CSRF token is sent as before. `webx-ui/module-auth` gains a test that the panel's routes answer an overridden method and still check the CSRF token.
