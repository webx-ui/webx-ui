---
'@webx-ui/core': minor
---

New component: `WxPopover` — a panel hung off a control, for a small form, a confirmation, or an
explanation too long for a tooltip.

It is the counterpart to `WxDropdown` rather than a second copy of it. Both stand on Reka's
popover, and the difference is what a click inside means: in a menu the click is the whole
interaction, so the panel closes; in a popover it is part of the work being done, so the panel
stays until the ×, a footer button, Escape, or a click outside. The panel has a heading, a footer
for the buttons and an arrow pointing at its trigger, takes focus when it opens and hands it back
when it closes, and is rendered in a portal so it is not clipped by a scrolling strip or a table.
