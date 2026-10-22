---
'@webx-ui/php': minor
---

`webx-ui/module-inbox`: the visitor's answers in the thank-you. `{{field.<name>}}` in the heading or
the text of a form's thank-you is the answer to that field — escaped, wrapped in
`<em class="webx-form__answer-value">` for the theme; an empty answer, an unknown name and a file put
in nothing. The same in the JSON answer and after a POST without JavaScript.
`<x-webx-inbox::form slug="…" again />` turns on «Send another».

`webx-ui/widgets`: `<x-webx-form again>` — after a thank-you the answer takes the place of the
fields and `webx-form__again` («Send another», `widgets.form.again` in ten languages,
`again-label`) brings them back empty with the focus on the first field; without JavaScript it is a
link to the same page. Off by default. `FormResponse::message()` takes an `HtmlString` heading:
printed as HTML (`heading_html` in the JSON and the flash), its text in `heading`
(`FormResponse::plain()`).
