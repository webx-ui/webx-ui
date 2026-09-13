---
'@webx-ui/module-media': patch
'@webx-ui/php': patch
---

Three things the file manager got wrong in Russian: the dialogs' buttons stood shoulder to
shoulder, the confirm button read `manager.save` because nobody had shipped the key, and an
upload refusal came back in English — the uploader is a bare `XMLHttpRequest` and was the one
request in the panel that never said which language it was drawn in.
