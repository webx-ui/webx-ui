---
'@webx-ui/core': minor
---

Date, time and date-time pickers: `WxDatePicker`, `WxDateTimePicker` and `WxTimePicker`, wrapping
`@vuepic/vue-datepicker`.

The model holds a string in the backend's format by default — `yyyy-MM-dd`, `yyyy-MM-dd HH:mm` or
`HH:mm` — so a value can travel to a Laravel API and back without conversion; an empty field is
`null`. `valueFormat` overrides it, and `valueFormat="date"` keeps `Date` objects instead.

The library's `--dp-*` variables are mapped onto WebX tokens, so the calendar follows the theme and
dark mode without its own `dark` prop. It stays external to our bundle so apps de-duplicate it.
