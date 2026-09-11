---
'@webx-ui/core': minor
---

Controls read at the size the navigation does

A select is a list of choices, and it now reads like the one in the sidebar:
14px, medium — the value in the field and the options in the panel. Checkbox and
radio labels come down to the same 14px, since a tick beside a label is a choice in
a list too.

Tabs go the other way. Given a strip wider than 600px they step up to 16px: at that
size they are page-level navigation rather than a control, and were reading a size
too small for the job. The question is put to the strip, not to the window, so tabs
in a narrow panel on a wide desktop keep the compact size.

`WxForm` gives its rows more room — `md` goes from 16px to 24px and `lg` from 24px
to 32px. At 16px a field's hint sat close enough to the next field's label to be
read as belonging to it.
