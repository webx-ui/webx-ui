---
'@webx-ui/php': patch
---

`webx:admin` accepts the password in `WEBX_ADMIN_PASSWORD` when there is nobody to ask, so a
provisioning script or a container entrypoint can create the first administrator. Still no
`--password` option: an argument lands in the shell history and in the process list.
