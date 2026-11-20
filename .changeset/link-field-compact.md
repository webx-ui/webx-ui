---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-menu': patch
'@webx-ui/module-inbox': patch
'@webx-ui/module-configurator': patch
'@webx-ui/module-blocks': patch
'@webx-ui/php': patch
---

`WxLinkField` — the `wx-link` field of a described screen — is one row now: what the link points at in two lines (the entity's name, its section and today's address with the anchor; the host and the full address; "No link"), badges for a new tab and each `rel` that is on, and `WxLinkPicker` itself, unchanged, in a panel under the row. The panel is a `WxPopover`: teleported, as wide as the row, opening upwards when there is no room below, closed by Escape, a click outside or "Done". The value, its validation and what the server keeps are what they were. The menu item's "Where it points", the form's redirect and thank-you button link (inbox) and the configurator's page use it. New props: `placeholder`, `label`; events `open`, `close`. Core gets a `ban` icon; module-admin gets the field's five words in every language.

Escape in a popover of a block's form or of a site-bar screen — the link field's panel, a select — closes that popover only, not the block's form or the drawer.
