---
'@webx-ui/module-pages': patch
'@webx-ui/php': patch
---

The bin of the pages section answers the search box.

`GET /api/cms/pages` read the term for the tree and dropped it for the bin, so an editor looking
for one deleted page among a hundred got the whole bin back and no sign that the box had been
ignored. The term now narrows both lists — and `pages_tree` with `trashed`, which had the same
gap, because an agent asking the bin for a name should not be handed everything in it either.

The section says the right thing when a search comes back with nothing: it used to answer “the
bin is empty” for any empty bin view, which was true until the box started working.
