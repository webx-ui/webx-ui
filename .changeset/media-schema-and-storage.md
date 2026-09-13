---
'@webx-ui/php': minor
---

`webx-ui/module-media` gains its schema and its storage: folders as a nested set, files that
belong to one, and the service that puts bytes on a disk and takes them off it. Keys are built
from a uuid and say nothing about the folder, so the same picture in two folders is two keys and
moving a file between folders never touches the bytes.
