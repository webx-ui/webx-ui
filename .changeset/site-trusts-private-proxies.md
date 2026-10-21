---
'@webx-ui/php': patch
---

`webx-ui/site`: a new site trusts the proxy in front of it from private networks and localhost (`trustProxies` in `bootstrap/app.php`), so that behind Traefik or a hosting platform its links, assets and redirects come out `https://` — no more mixed content and a blank `/cms`. A public address is still not believed. The skeleton's own tests now run in the smoke right after `create-project`.
