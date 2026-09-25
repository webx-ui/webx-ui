---
'@webx-ui/core': patch
---

`WxRichText` no longer writes its model when it is disabled and enabled again. Tiptap emits an
update on `setEditable`, and the update wrote the document back in Tiptap's own HTML — so an
editor screen that locks its form while publishing came back with unsaved changes, autosaved
them, and showed the record as "edits" right after it went live.
