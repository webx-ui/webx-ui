---
'@webx-ui/php': minor
---

`webx:modules [--json]` lists the modules a site can have — id, package, npm half, what each requires and whether it is installed — without touching the database. `webx:module:add <vendor/package> [--no-build]` installs one: `composer require`, `webx:panel --sync`, `npm install` and the build, safe to run twice and failing on the first step that fails. The catalogue behind `webx:setup` now names each module's npm half and requirements, and `webx:setup --modules=` brings the required modules along.
