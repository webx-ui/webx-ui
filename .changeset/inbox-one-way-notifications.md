---
'@webx-ui/module-inbox': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-pages': patch
---

The Notifications tab of a form is one card: the field with the visitor's address on top, the
switch «How to tell about a submission» — by letter to addresses or through notification channels —
and only the chosen way below it; without the notifications module there is no switch. «Notify
through» lists the channels of the event as soon as it is opened, typing filters them, a channel
that is off is marked, and an empty target says «No channels yet».

«After sending» is a link like a menu item's: a page of the site, an address, or nowhere (the
thank-you in place of the form). The field's dialog asks for the type, then the label, and keeps
the name under «Advanced»: a new field is named after its label (`vash_telefon`, `_2` when taken)
until somebody writes a name, and renaming one warns about `{{field.<old>}}` in channel templates.
«Delete» on the bar of selected submissions has no icon.

`WxRelationsField` searches when its box is opened, takes `none-text` for a target with nothing to
offer and shows a candidate's own `mark`. `slugify(title, separator)` moved to
`@webx-ui/module-admin`; `@webx-ui/module-pages` re-exports it.
