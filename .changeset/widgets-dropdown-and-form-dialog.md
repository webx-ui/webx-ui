---
'@webx-ui/php': minor
---

`webx-ui/widgets`: `<x-webx-dropdown>` — a panel tied to its trigger (`placement`, `open-on="click|hover"`), a `<details>` without JavaScript and a popover with it, turned over at the window's edge, closed on Esc, Tab out and a click elsewhere, one open on the page. And a form of `module-inbox` in a dialog: any link or button with `data-webx-form="<slug>"`, or a link to `#webx-form-<slug>`, opens it; the page gets the form once, before `</body>`, placed `modal`; `data-webx-form-value-<field>` fills a field of it; after sending the dialog shows the thank-you and waits for Close. `theme-default`'s kitchen sink has a page for both.
