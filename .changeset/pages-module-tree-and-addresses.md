---
'@webx-ui/php': minor
---

`webx-ui/module-pages`: the tree of pages, the home page as its root, and the addresses.

A new Composer package. Pages are a nested set with translatable `title` and `slug`, content
made of blocks, a draft and a history — almost all of it from packages that already existed.
What the package adds is the rules that are about pages: the home page is the root, is always
there, and cannot be moved, deleted or given an address of its own; there is only ever one of
it; and deleting a page trashes its whole branch, one node at a time, so that every address in
it is released and a restore brings back exactly what went down together.

`webx-ui/nested-set` learns soft deletes, by opt-in: a model that says `softDeletesInTree()`
keeps its trashed nodes standing in the tree instead of being refused the delete, and takes
charge of what happens to their descendants. Without it, the delete is still refused — a node
that vanishes while its bounds are reclaimed leaves its children outside their parent.

`webx-ui/routing` learns that an entity may have no address in a language at all:
`HasUrl::hasUrlIn()` answers yes for everything unless a model says otherwise, and a language it
says no to gets no row in the registry and loses the one it had. Syncing also stopped writing
the slug it read back into the entity, which quietly filled in languages the editor had left
empty; `webx:routes:check` no longer reports those languages as missing addresses.
