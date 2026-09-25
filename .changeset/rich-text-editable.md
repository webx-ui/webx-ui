---
'@webx-ui/core': patch
---

`WxRichText` no longer writes to its model when it is locked and unlocked. Tiptap raises an update on
`setEditable`, and the editor wrote the same empty words back in another shape — a translatable
field the server sent as `[]` became `{ en: '' }` — so a form locked for the length of a request
(publishing a recipe) saw a change after it and autosaved a draft: "edits" came back a second
after Publish. The editor now switches without the update and skips a write that changes nothing.
