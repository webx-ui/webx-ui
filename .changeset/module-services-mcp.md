---
'@webx-ui/php': minor
---

`webx-ui/module-services` for an agent: `services_list`, `services_get`, `services_create`,
`services_update`, `services_publish`, `services_unpublish`, `services_delete` and
`services_reorder` go through the same list, screen and order code as the panel — a field a
project patched onto `services.form` is written and refused by an agent exactly as by an editor,
and a reorder with `category` moves only that category. The categories get the shared
`service_categories_*` tools, and `services://catalog` gives an agent every category with its
services in its order, drafts included, before it writes. `webx:demo` seeds three categories and
eight services with covers and blocks, one of them in two categories at a different place in each.
