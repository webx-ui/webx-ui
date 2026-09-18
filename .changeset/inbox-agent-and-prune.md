---
'@webx-ui/php': minor
---

`webx-ui/module-inbox`: the section by its other doors, and the command that forgets.

Six MCP tools under `inbox:read` and `inbox:write` — `inbox_forms_list`, `inbox_form_get`,
`inbox_form_save`, `inbox_list`, `inbox_get`, `inbox_set_status` — through the same rules the
panel's own editor is refused by: the rules and the row of a form and of a field now live in
`FormInput` and `FieldInput`, which the form requests and the agent both go through, so a slug
that is not an address is refused at either door. A save changes only what it names, and a field
sent with `remove: true` is put aside rather than destroyed, which leaves the answers already
given through it readable.

Receiving a submission is deliberately not a tool, and neither is deleting one. What deletes is
`webx:inbox:prune`: spam older than one age, everything older than another, both from the config
and both nought by default, row by row through the model so the files on the disk go with them.
