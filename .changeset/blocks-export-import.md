---
'@webx-ui/module-blocks': minor
'@webx-ui/php': minor
---

Blocks move between sites from the panel. **Export** in the «Blocks» section — and on a type's
editor — saves the ticked types as one `.json` pack together with every component they call, so
the file works on a site that has none of them. **Import** reads such a pack (or a file written by
`webx:blocks:export`), shows what each type would become — new, updated, unchanged or refused —
before writing anything, then brings the types in as drafts, optionally publishing what passes
the checks. `webx:blocks:import` and the panel share one importer, and the command reads packs too.
New routes: `GET /blocks/export`, `POST /blocks/import`.
