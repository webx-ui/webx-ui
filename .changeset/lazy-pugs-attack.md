---
'@webx-ui/core': minor
---

A round of fixes from reading the docs on a phone.

- `WxSelectionArea` now picks the item a finger taps. It used to ignore touch entirely unless
  `touch` was on, so a phone selected nothing at all; a finger that travels is still left to the
  scroller, and a cancelled gesture no longer picks whatever it started on. With `touch` on, the
  area sets `touch-action: none` — without it the browser took the drag away on a real device,
  which is why it worked in a desktop emulator and nowhere else.
- `WxSteps` turns down the page on its own once a step would be narrower than `minStepWidth`
  (132px; `0` never folds). Across a phone the titles used to wrap to a letter a line.
- `WxDescriptions` clamps a pair's `span` to the columns the list has, and folds every span to one
  when the list folds to one column. A `:span="2"` pair used to ask for more tracks than the grid
  had and threw the placement of every pair after it.
- `WxMenu` shuts an open overflow flyout before re-splitting the bar, instead of leaving it
  standing with an empty list.
- `WxSubmenu` sets the first child of an open branch off its own title, rather than leaving the
  same two pixels there as between siblings.
- `WxTree` gains `springDelay` (600ms): holding a node over a closed branch opens it, as
  `WxTable`'s tree already did.
- `WxDateRangePicker` no longer offers a clock under the calendar — the date-only format threw
  away whatever was set on it.
- `WxTooltip`'s default `delay` is 150ms rather than 400ms.
