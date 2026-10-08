---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-blocks': minor
'@webx-ui/php': minor
---

`wx-rich-text` takes `inline`: one line with bold, italic and an accent instead of a document —
for a heading like `Deeply heard<span>.</span> Gently guided` that used to sit in a plain input
with its tags on show. The value is the bare line, no paragraph around it; Enter does nothing and
pasted lines become one. On the server an inline field keeps `<strong>`, `<b>`, `<em>`, `<i>` and
a bare `<span>`, drops every attribute, flattens blocks into the line and is held to 2000
characters. Until a block type moves such a field over, a plain input whose value holds tags
says so under it and keeps the value exactly as stored.
