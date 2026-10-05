---
'@webx-ui/module-admin': patch
'@webx-ui/module-media': patch
---

The rich text editor of the panel opens a document with its library pictures pointing at where
they live now, not at the host the paragraph was written on: `WxRichTextField` asks the library
through a new `assetUrls` seam of a module (offered by `media()`) before handing the document to
the editor.
