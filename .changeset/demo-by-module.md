---
'@webx-ui/php': minor
---

`webx:demo --module=<id>` seeds and removes demo content one module at a time, so that a
module installed after the first seed can be filled beside the journal instead of emptying the
site. A module the journal already holds is refused unless `--force`; a requirement whose demo is
not seeded yet comes along first; `--remove --module` takes out only that module's entries and is
refused while another seeded module still requires it. Without `--module` nothing changes.
