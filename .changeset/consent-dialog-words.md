---
'@webx-ui/php': minor
---

Widgets: the buttons of the cookie dialog take their words from keys of their own — `consent.dialog_reject`, `consent.dialog_accept` (and `consent.save`), in all 10 languages, by default the same as the banner's `consent.reject` / `consent.accept`. A site words the banner and the dialog apart in `lang/vendor/webx-widgets` instead of copying the `consent` view; a site that overrides nothing prints what it printed before.
