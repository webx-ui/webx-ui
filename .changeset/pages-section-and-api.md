---
'@webx-ui/module-pages': minor
'@webx-ui/php': minor
---

The pages section: the tree of a site's pages, and the panel API behind it.

`@webx-ui/module-pages` is a new npm package — the front end of the section. The list is a table
tree read a level at a time: the home page is pinned at the top and its children are the top
level, because everything on the site is inside it and a branch drawn for that would give every
row a step of indentation that says nothing. Children arrive when a branch is opened, searching
puts the tree away and answers with a flat list of matches and their addresses, and the bin is a
filter rather than a section of its own. A page is moved by dragging it or through “Move…” and a
tree of pages — the one that works on a touch screen and in a catalogue where the page and its
new parent are four screens apart — and either way the section says out loud how many addresses
the move rewrote, because an editor should not hear about a thousand redirects from a search
engine. Row actions: open, add a page inside, duplicate, move, copy the address, open on the
site, delete; in the bin, restore.

`webx-ui/module-pages` gains the section and the endpoints under `/api/cms/pages`: the level of
the tree with `can` and `children_count` on every row, create, save the draft, move, duplicate,
publish, unpublish, delete into the bin with the branch, and restore. A page's title comes from
its draft and its address from the registry, so a page renamed and not yet published shows its
new name beside the address the site is still serving. Its refusals — the home page cannot be
moved or deleted, a page cannot be dropped into its own branch, nothing stands beside the home
page — answer as a 422 under the field they are about, the same way a taken address does.
