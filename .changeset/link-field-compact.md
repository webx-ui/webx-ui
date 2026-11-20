---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-menu': patch
'@webx-ui/php': patch
---

`WxLinkField` — the `wx-link` field of a described screen — is one row now: what the link points at in two lines (the entity's name, its section and today's address with the anchor; the host and the full address; "No link"), badges for a new tab and each `rel` that is on, and `WxLinkPicker` itself, unchanged, in a panel under the row. The panel is a `WxPopover`: teleported, as wide as the row, opening upwards when there is no room below, closed by Escape, a click outside or "Done". The value, its validation and what the server keeps are what they were. The menu item's "Where it points" uses it. New props: `placeholder`, `label`; events `open`, `close`. Core gets a `ban` icon; module-admin gets the field's five words in every language.
