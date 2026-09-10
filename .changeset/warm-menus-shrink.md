---
'@webx-ui/core': patch
---

Fixes four things in the pickers.

The time-only menu was sized for a calendar it never shows, leaving about 150px
of empty space around the columns; it now passes a smaller `modeHeight`.

The menu clips its content, so its rounded corners stay round. `.dp--overlay` is
an opaque square panel inset a pixel from the menu edge — it has to be opaque,
since it covers the calendar when the month or year list opens — and it painted
over each corner.

The library's pointer is dropped. `.dp--arrow-top`, unlike its bottom twin, never
gets a horizontal position, so it landed on the menu's left corner — invisible at
the library's 4px radius, a white shape beside the corner at ours.

`WxTimePicker` and `WxDateTimePicker` were handing `WxDatePicker` an explicit
`false` for every boolean prop the caller had not set, because Vue casts an absent
boolean prop to `false` and the presets forwarded their whole prop object. That
silently turned off `is24`, `clearable`, `autoApply` and `teleport` — the last of
which would clip the menu inside a `WxCard`.
