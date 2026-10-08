---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-blocks': minor
---

Text fields help to type placeholders. `WxInput`, `WxTextarea` and `WxRichText` take `tokens`
(`{ name, value?, description? }[]`): typing `[` opens a list filtered by what follows, with each
placeholder's current value beside it, and Enter, Tab or a click writes `[name]` in place of what
was typed; a button in the field (the toolbar, for the rich text) lists them all and inserts the
one chosen at the caret. Known placeholders already in the text are drawn as chips, while the
value stays the plain `[name]` — a mirror behind the input and the textarea, a decoration in the
rich text, the inline one included; `[[name]]` and unknown names stay text. The words are props:
`tokensTitle` and `tokensLabel`.

The panel fetches the site's shortcodes once (`loadShortcodes`, `useShortcodes` in
`@webx-ui/module-admin`), and the block editor offers them in every text field of a block and of a
region — plain inputs, textareas and rich texts, inside repeaters too, but not in e-mail, address
or phone inputs.
