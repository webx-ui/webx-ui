---
'@webx-ui/core': minor
---

`WxRichText`: a WYSIWYG editor built on Tiptap — formatting, headings, lists, quotes, tables with
row and column controls, links, images and YouTube embeds. The model is an HTML string, and an empty
document is an empty string rather than `<p></p>`.

The editor never uploads anything itself. `upload(file)` handles pasting, dropping and the toolbar
button; `pickImage()` is the seam a media library plugs into. Each file is inserted only once its
upload resolves, so a failure leaves nothing half-inserted. Without either prop the image button is
not rendered.

Tiptap is a dependency but stays external to our bundle, so apps that never import the editor do not
ship it.
