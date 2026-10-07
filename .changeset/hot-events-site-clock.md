---
'@webx-ui/core': patch
'@webx-ui/module-admin': patch
'@webx-ui/module-events': patch
---

Moments are shown and entered on the site's clock: `WxDatePicker` takes a `timezone` (IANA zone) for a value whose format carries an offset, or reads one provided with `provideDateTimezone` / `dateTimezoneKey`, and names that zone's offset after the time when it is not the reader's. The panel provides the manifest's `timezone`, so an event at 09:30 on the site reads 09:30 to an editor anywhere and goes back with the site's offset. The events editor no longer moves the days of an event of days onto the reader's clock.
