---
'@webx-ui/core': minor
'@webx-ui/schema': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-blocks': minor
'@webx-ui/module-inbox': minor
'@webx-ui/module-media': patch
'@webx-ui/module-seo': patch
'@webx-ui/php': patch
---

A round of panel fixes, mostly from looking at the two demo sites on a phone.

- A field stops at a width it can be read at: `WxFormItem` caps its control at
  `--wx-field-max-width` (640px), and what is not a field in that sense says `wide` — a prop on
  the item, `wide: true` on a registry entry.
- A hovered table row and the `···` at its end no longer paint themselves the same grey: the row
  goes a tone softer, and the row menu carries no fill at rest.
- Tooltips never open on a touch screen, where the tap that opens one is the tap that was meant
  for the button under it. `useHoverPointer()` is the question, asked once for the application.
- The panel's step reaches what the panel teleports out of itself — drawers, dialogs, the toaster
  — so a phone no longer lays one screen out with desktop air.
- Blocks: a row of the tree offers its actions as the panel's `···` rather than three icons that
  only appeared on hover, and removing a block always asks first.
- Inbox: the form editor no longer draws its save bar across the middle of the form, the section's
  panes stay inside the card's corners, the recipient's bin is red and asks, and a submission
  keeps its notes, its log and its metadata in one card with three tabs instead of three cards.
