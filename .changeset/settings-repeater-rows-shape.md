---
'@webx-ui/php': patch
---

A row of a `wx-repeater` with a key none of its fields names is refused under that row and key — `contacts.addresses.0.street`, with the keys a row takes — instead of being dropped while the write reported success; a key the field already held still goes through, so an older row saved from the panel does not fail. `settings_list` and `settings_get` describe what one row of a list takes (`item`: keys, value type, languages, options, bounds). An address of the contacts needs its text, and its coordinates come as a pair. A test reads `menu://menus` in all ten panel languages and fails on a link type named by its translation key.
