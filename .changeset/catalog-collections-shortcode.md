---
'@webx-ui/php': minor
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-blocks': minor
---

`[product-collection key=cameras view=carousel limit=4]` puts a product collection in a rich text. `webx-ui/module-admin` gets block shortcodes: `Shortcodes::register(block: true)` prints only in `htmlIn()`, out of its paragraph, and nothing in a line or in plain text; `register(menu: fn)` gives the panel's `[` a ready bracket per row in place of the bare name, and `GET /api/cms/shortcodes` answers with those rows. `htmlIn()` and `PageText::html()` take `blocks: false` for HTML that leaves the page. Text no longer comes from the printed HTML: `SeoData::fallback()` takes the stored lead and reads its shortcodes before the cut, category and author descriptions, the `Person` of an author and the answers of `FAQPage` pass it the stored text, the description of `JobPosting` and the description variables of feeds leave block shortcodes out, and a question whose answer has no words of its own gets no `FAQPage` entry. `TokenOption` in `@webx-ui/core` takes `text`, the whole bracket a row writes, so several rows can share a name. `@webx-ui/module-admin` and `@webx-ui/module-blocks` keep block shortcodes out of lines and paragraphs.
