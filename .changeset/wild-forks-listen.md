---
'@webx-ui/php': minor
---

Page and article screens stop asking the constructor to fill the screen

`"props": { "fill": true }` is gone from the `wx-blocks` node of `pages.form` and
`blog.article-form`. It told the field to be exactly one window tall and scroll each of its panels
inside itself; the field does not do that any more, because its preview is now as tall as the page
it shows and the panel scrolls it. Nothing replaces the prop — the screens simply stop passing it.

`webx-blocks` also gains three lines in all ten languages: the name of the width switcher, and the
steps to the previous and the next block, which the form's head carries now that the tree is not on
screen beside it.

`webx-admin` gains two — "Saving…" and "Saved" — which are what a screen reader hears from the mark
that replaced the word in the editors' bars. The lines those bars used to print (`state-saving`,
`state-saved`, `state-unsaved` under `webx-pages` and `webx-blog`) go, because nothing prints them.
