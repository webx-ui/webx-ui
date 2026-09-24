---
'@webx-ui/module-admin': minor
---

The panel half of `wx-collection`: the field a block uses to show another section's records.
It asks `GET /api/cms/collections` which sections this administrator may place, lists the chosen
section's categories (none chosen — all of them), and keeps a limit, a filter switch and — when
the section can mark its records up — a markup switch that shows what the rule gives until the
editor sets it, with a way back to the rule. The value is only the choice
(`{ categories, limit, filter, markup }`), never the records. Exported as `WxCollectionField`,
with `collectionSources()`, `normaliseCollection()` and `defaultMarkup()`; the words are under
`webx-admin::collections.*`.
