---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
---

Date pickers are drawn in the language they are asked for

`@vuepic/vue-datepicker` bundles `en-US` and nothing else, so every calendar in the kit headed a
Russian screen with "Sep 2026" over a "Mo Tu We" row. `WxDatePicker`, `WxDateTimePicker`,
`WxTimePicker` and `WxDateRangePicker` now take a `locale` prop — a BCP-47 tag, whose month and
weekday names come from the browser's own `Intl` data rather than an imported language pack — and
`provideDateLocale` / `dateLocaleKey` say it once for a whole application. The library's own
date-fns locale object is still accepted. With nothing given, the browser's language is used.

The panel hands every picker below it the language the interface is drawn in, so a calendar follows
the administrator's choice rather than their browser's setting.
