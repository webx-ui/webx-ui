---
'@webx-ui/php': patch
---

A snapshot restore no longer breaks a container stand. Run as root (`docker exec`),
`webx:snapshot` and `webx:snapshot:restore` hand everything they wrote back to the owner of
`storage`, and refuse before writing when they can neither do that nor share its group. `webx:doctor`
checks that the web server's user (`WEBX_WEB_USER`, else the owner of `storage`) can write where
the site writes at run time and, as root, lists paths under `storage` owned by somebody else.
`module-seo` keeps the target's `seo.normalise-*` through a restore, and neither its address
normalisation nor its redirects table answers the health route or a loopback probe without
`X-Forwarded-*`. `SnapshotTables::preserve()` now adds up across packages.
