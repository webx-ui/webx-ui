---
'@webx-ui/module-admin': patch
---

The shared list of categories no longer prints "No address in this language" under every row
when the module's categories have no addresses at all (`prefix: null`), as the FAQ's don't.
