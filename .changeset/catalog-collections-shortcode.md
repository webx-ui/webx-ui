---
'@webx-ui/php': minor
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-blocks': minor
---

`[product-collection key=cameras view=carousel limit=4]` puts a product collection in a rich text. `webx-ui/module-admin` gets block shortcodes: `Shortcodes::register(block: true)` prints only in `htmlIn()`, out of its paragraph, and nothing in a line or in plain text; `register(menu: fn)` gives the panel's `[` a ready bracket per row in place of the bare name, and `GET /api/cms/shortcodes` answers with those rows. `TokenOption` in `@webx-ui/core` takes `text`, the whole bracket a row writes, so several rows can share a name. `@webx-ui/module-admin` and `@webx-ui/module-blocks` keep block shortcodes out of lines and paragraphs.
