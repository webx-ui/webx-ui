---
'@webx-ui/php': minor
---

The site audit reports block values for fields their type does not define (`blocks.stray_values`, a notice): one finding per entity, a row per block with its type, key, the stray keys and whether they are on the site or only in the draft, and a link to the editor. The fix `blocks.prune-stray` takes them out of that one entity, dry run first. Repeater items are measured against the repeater's own fields. The check, the fix and `webx:blocks:prune` share one `StrayValues`; a block of an unknown type is never touched.
