---
'@webx-ui/php': minor
---

`webx:boot` is what a container does between starting and serving — waiting for the database, migrating, keys, languages, block types, the first administrator, caches — worked out from the modules installed rather than written into a script; the skeleton ships the Dockerfile and the two compose stacks that call it
