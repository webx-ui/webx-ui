---
'@webx-ui/php': minor
---

Block values for fields a type does not have can be taken out: `blocks_edit_content` has an `unset` op (`{ op: "unset", key, fields }`, dry run included), and `webx:blocks:prune [--dry-run]` removes them from every listed entity and the regions, live and draft. The outline names a block by what its fields mean (`BlockLabel`): a heading-like field, then the first text field of the schema, in the content language — never a picture, a choice, an address or a stray key; the type's title when nothing fits.
