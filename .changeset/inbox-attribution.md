---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
---

Inbox and widgets: a submission knows where the visitor came from. `<x-webx-form data-webx-attribution>` (the Inbox's forms have it) loads the widgets' `attribution.js`, which adds the `utm_*` and the outside referrer of the last touch, the landing page and the campaign of the first touch to what is sent. Without consent to statistics nothing is stored and only the page with the form counts; with it the first and the last touch are kept in `localStorage` for 90 days, removed when consent is taken back. No `gclid` / `fbclid`. The submission's `meta` gains `utm_first` and `landing` beside `utm` and `referrer` (only `utm_*` keys, values cut to 255), shown on the panel's card, in the letter and in `inbox_get`; `webx-inbox.attribution` (`WEBX_INBOX_ATTRIBUTION=false`) turns it off.
