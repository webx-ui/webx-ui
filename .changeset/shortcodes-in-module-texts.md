---
'@webx-ui/module-admin': minor
'@webx-ui/module-inbox': patch
'@webx-ui/php': minor
---

Shortcodes work in the texts of the modules, not only in blocks. A screen's `wx-rich-text` offers the site's shortcodes behind the `[]` button by default (`props.shortcodes: false` switches it off), and a `wx-input` or `wx-textarea` does when its node says `props.shortcodes: true` — a product's summary, an article's lead, a review. On the site those fields are printed through the new `WebxUi\Admin\Shortcodes\PageText` (`html()`, `text()`, `plain()`, `plainFromHtml()`), so a page, a card in a list, a feed and the JSON-LD of one record all show the value, never a bare `[phone]`; the record keeps the bracket. The inbox's thank-you and consent sentence take them too, printed before the visitor's answers go in. `module-admin` exports `takesShortcodes()` and `useShortcodesWhen()` for a module that draws an editor of its own.
