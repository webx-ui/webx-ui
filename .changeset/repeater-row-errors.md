---
'@webx-ui/core': minor
'@webx-ui/schema': patch
---

A refusal of one row of a repeater lands under that row's field. `WxRepeater` takes `rowErrors`
— the errors of each row, by position — and then every row answers to its own and nothing else:
before, a row's `title` inside a form showed the error of the form's own `title`, in every row at
once. A refused row unfolds by itself and its header turns red. `wx-repeater` on a described
screen hands the server's `articles.2.url` and `articles.2.title.en` to the third row's `url` and
`title` without anything to set.
