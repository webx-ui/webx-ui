---
'@webx-ui/core': minor
'@webx-ui/schema': minor
'@webx-ui/php': patch
---

`WxRepeater` takes `confirmRemove`: the bin asks in a popover first (`removeQuestion`,
`cancelLabel`). A repeater on a screen asks by default, in the panel's words
(`webx-admin::screens.repeater.remove-question` and `.cancel`), and stops at a field's width
unless its rows lay fields side by side or hold an editor — a `TypeEntry`'s `wide` may now be a
function of the node.
