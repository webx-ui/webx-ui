---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
---

inbox, widgets: booking a meeting in a form of steps (FORM_STEPS §3.6, §6.7, §16), every option off by default. A «Date and time» field takes `timezone: visitor` (the grid and «today» in the browser's zone, sent as `[timezone]` and checked on intake; the site's zone without JavaScript), `lead-days`, `time-skip`, `slots: panel` (the day with its weekday heads the times, the zone inside the panel, the week in two letters) and `first-day` (the nearest open day chosen at once). A form takes `steps.hold-next` («Next» `aria-disabled` until the step is answered), `dialog.scroll: page` (the dialog scrolls as a whole with its backdrop — `<x-webx-dialog page>`, `webx-dialog--page`) and `thank-you.button` / `thank-you.button-link` (the button under the thank-you; a link to the page itself closes the dialog and scrolls to the top). All of them in the panel and through MCP.
