---
'@webx-ui/php': patch
---

`module-events`: `events_restore` and `events_purge`; every events tool refuses an argument or a field it does not know; a dry run is the real call rolled back (create, update, duplicate, publish, restore); `events_unpublish` refuses what is not on the site and `events_delete` what is already in the bin; `has_draft` is true for a never-published event, a copy included; `events_get` lists a project's screen field as null until written; the panel's writer, list filters and the catalogue use the content language rather than the interface's.
