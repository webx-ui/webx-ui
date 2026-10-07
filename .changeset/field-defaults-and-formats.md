---
'@webx-ui/schema': minor
'@webx-ui/module-blocks': patch
'@webx-ui/php': minor
---

A screen node takes `default`: a field with nothing stored is drawn with it and the site reads it (`settings()`, `extra()`, repeater rows, block fields), while an untouched field still saves nothing; the server checks a default by its type's rules. `wx-input` with `props.type` `email`, `url` or `tel` holds that format on the server too, in repeater rows as well.
