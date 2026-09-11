---
'@webx-ui/core': minor
'@webx-ui/tokens': minor
---

Controls read at 14px, and the size is a token now

Every control — input, textarea, number, select, tags, autocomplete, cascader,
colour, the date fields and their calendar, checkbox, radio, switch, rate, button —
took its text size straight off the body scale, which put the default at 16px. That
is a size for reading paragraphs. An admin panel is a page of controls, and beside
navigation at 14px they were reading a size too large.

The scale drops a notch: `md` from 16px to 14px, `sm` from 14px to 12px, `lg` from
18px to 16px. Control heights are unchanged — `data-density="compact"` is still the
way to tighten those.

The size is no longer read off the body scale at all. `@webx-ui/tokens` gains
`--wx-font-size-control-sm`, `--wx-font-size-control-md` and
`--wx-font-size-control-lg`, and every control points at them, so retuning how
controls read is three lines in a stylesheet rather than an override per component.
