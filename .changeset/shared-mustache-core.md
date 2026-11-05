---
'@webx-ui/core': minor
'@webx-ui/module-notifications': patch
---

The Mustache preview moved from `@webx-ui/module-notifications` to `@webx-ui/core`: `renderTemplate`, `TemplateRefusal`, `knows`, `escapeHtml`, `htmlPlain`, `snippetOf`, with the `mustache` dependency; the notifications keep exporting what they exported. New in core: `escapeForFormat` (xml, json, csv, text), `refuseInCdata`, `templateMessages`, values given as `{ $cdata }`, and `first` and `last` on the elements of every list, in the preview and in the editor's suggestions.
