---
'@webx-ui/php': minor
---

New library `webx-ui/templates`: the Mustache of the notifications, for everything that keeps a template in the database. `Template`, `TemplateError` and `Html` moved there from `WebxUi\Notifications\Templates` to `WebxUi\Templates`, with no alias; the refusals moved to `webx-templates::errors`. `Events\Variable` of the notifications now extends `WebxUi\Templates\Variable`. New: `Template::compile()` for rendering many records, `Escapes` (xml, json, csv, text), `Cdata`, `Template::refuseInCdata()`, `first` and `last` on the elements of every list. `module-notifications` no longer requires `mustache/mustache`.
