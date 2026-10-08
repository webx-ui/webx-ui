---
'@webx-ui/core': minor
'@webx-ui/module-admin': patch
'@webx-ui/php': patch
---

`WxRichText` shows and edits its HTML. A `</>` button (tool key `source`, on by default) turns
the field into a code editor with the document one block to a line; what is typed reaches the
model at once, and on the way back the editor names any tag or attribute its schema would drop
and asks before removing it. A read-only field opens its source to be read. The panel's editor
carries the new words in all ten languages.
