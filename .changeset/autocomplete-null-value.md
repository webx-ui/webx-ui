---
'@webx-ui/core': patch
---

`WxAutocomplete` takes `null` as an empty field. The model's default covered a missing value, not a null one, so a record that sent `null` for its text — a row of a screen's repeater shown only when a switch says so — threw while rendering, and the field came up with no input until something else redrew it.
