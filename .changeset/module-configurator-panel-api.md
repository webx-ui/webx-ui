---
'@webx-ui/php': minor
---

`webx-ui/module-configurator`: the panel's API of the section — the list, the record with its
revision (409 when stale), the draft, publish (422 with every problem of the schema), unpublish,
discard, versions and their restore, the bin, `validate` and `quote` — over the same writer and
quoter as the MCP tools. The field type `wx-configurator` (a configurator by key, refused when
written anew with a key nobody has) and the relation target `configurator` for its picker. The
editor's heartbeat knows the record. The section's words in ten languages.
