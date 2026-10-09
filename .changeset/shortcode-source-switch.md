---
'@webx-ui/php': minor
'@webx-ui/module-settings': minor
'@webx-ui/module-admin': minor
---

«Settings» → «Shortcodes»: a row says with a switch whether it prints its own value or a setting,
and shows only the field it uses. The setting is picked from a list of the text fields on the
settings screen — labelled where they live, with the key and what they hold now — and a key the
settings do not have is refused on save, under the row, instead of printing nothing. A row saved
before the switch existed reads a filled-in key as a setting, as it did. `WxScreen` takes a `patch`
of the page's own, laid under the project's.
