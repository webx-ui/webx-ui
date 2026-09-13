# @webx-ui/module-media

## 0.1.0

### Minor Changes

- 47cc998: The file manager after an hour with it: icon actions instead of labelled buttons, filters behind
  popovers, folders in a drawer on a phone, case-insensitive search in any alphabet, previews that
  change when a picture is edited, and a copy-the-link that says whether it worked.

  `@webx-ui/admin` gains the toaster the panel never had — until now every `toast()` from every
  module reported into silence.

- 47cc998: New package `@webx-ui/module-media`: the file manager as a section of the panel, a picker that
  opens from code, and a form field that keeps `{ path, alt, title }` on the entity rather than on
  the file. Batch deletion moved to `POST files/delete` on the server, because the panel's own HTTP
  client sends no body on `DELETE`.
- 47cc998: A second pass over the file manager, from using it: upload refusals written in extensions rather
  than a paragraph of mime types, a status bar under the grid instead of a toolbar that grows a
  line, folders created through a dialog rather than a `prompt` the browser may refuse, the page in
  a card, and the image editor cropping what the person actually framed.

  `@webx-ui/core`: `WxFileCard` falls back to the old clipboard when the modern one refuses, so the
  green tick appears wherever the copy actually worked.

### Patch Changes

- 47cc998: Three things the file manager got wrong in Russian: the dialogs' buttons stood shoulder to
  shoulder, the confirm button read `manager.save` because nobody had shipped the key, and an
  upload refusal came back in English — the uploader is a bare `XMLHttpRequest` and was the one
  request in the panel that never said which language it was drawn in.
- Updated dependencies [47cc998]
- Updated dependencies [47cc998]
  - @webx-ui/admin@0.2.1
  - @webx-ui/core@0.14.3
