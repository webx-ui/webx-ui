---
'@webx-ui/php': patch
---

Agents cannot put stray block values in: `blocks_edit_content` (`set`, `add`) and `blocks_set_content` refuse a value for a field the block's type does not define — repeater items included, dry run included — naming it and the type's fields; a stray value the block already holds may be written back as it was or emptied. A prune (`webx:blocks:prune`, the audit fix `blocks.prune-stray`) now drops a draft that differed from the site only by stray values, and says so.
