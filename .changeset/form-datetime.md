---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
---

Forms: the «Date and time» field (FORM_STEPS §6). The widgets' `<x-webx-field type="datetime" :step from to :weekdays timezone>`
asks for a day and a time in the site's zone and sends `<name>[date]` and `<name>[time]`: without
JavaScript the browser's date and a list of the day's times, with it `datetime.js` (claimed only by such
a field) draws a calendar of a month — the grid of the APG date picker, days gone and days not offered
`aria-disabled` — and the times of the chosen day as a group of radios, «today» counted in the site's
zone. Inbox: the field type `datetime` with `time-step`, `time-from`, `time-to`, `weekdays` and
`timezone-label`; the zone is `contacts.timezone` of the settings, else the application's. The intake
refuses a time off the grid, a day of the week not offered and a moment already gone; the answer keeps
«12/14/2026, 10:30 AM (Europe/…)» and a payload `{ date, time, timezone, at }`, and a column of the list
sorts by `at`. Saving refuses `datetime.range`, `datetime.step` and `datetime.weekdays`; the panel's
field dialog sets the grid with a preview of the day's times, `inbox_form_save` takes it and its dry
run says the times of a day.
