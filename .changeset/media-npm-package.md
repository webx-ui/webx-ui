---
'@webx-ui/module-media': minor
'@webx-ui/php': patch
---

New package `@webx-ui/module-media`: the file manager as a section of the panel, a picker that
opens from code, and a form field that keeps `{ path, alt, title }` on the entity rather than on
the file. Batch deletion moved to `POST files/delete` on the server, because the panel's own HTTP
client sends no body on `DELETE`.
