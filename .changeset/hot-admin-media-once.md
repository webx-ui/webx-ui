---
'@webx-ui/module-media': patch
---

The library asks for the files of the folder it opens once, not twice: opening the first folder no longer loads the list both on mount and from the folder watcher, and a reload after an upload or a move no longer asks for the list twice either.
