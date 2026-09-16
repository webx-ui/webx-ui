---
'@webx-ui/php': minor
---

Agents get the pages of a site. `webx-ui/module-pages` offers nine tools — `pages_tree`,
`pages_get`, `pages_create`, `pages_update`, `pages_move`, `pages_publish`, `pages_unpublish`,
`pages_delete`, `pages_restore` — through the same doors the panel uses: `PageForm` decides what
a page's values are and checks them against the described screen, so a field another module put
on `pages.form` is writable here by having done nothing, and the `revision` that answers a
panel's 409 refuses an agent's stale write with the same sentence. Where a page may go is now
`Placement`, one class the move endpoint and the move tool both ask, rather than two copies of
the three rules that keep the home page where it is.

A page is named by its id or by its address — `"/catalog/shoes"`, and `"/"` for the home page —
because that is what a site is talked about in; text fields answer with every language at once,
since an agent that got one title has no way of knowing whether the others exist. Content does
not travel through any of this: a `blocks` key sent to `pages_update` is refused with the name of
the tool that does it (`blocks_edit_content`, §13.1), rather than accepted and dropped. The new
resource `pages://sitemap` is the map to read first — the tree nested the way it is nested, each
page with its address per language and its status — and the prompt `build_page` puts the loop in
front of an agent that was asked for a page: read the map and the block catalogue, create, fill
with blocks, look at the preview, write the SEO card, and leave it a draft for a person.

`webx-ui/mcp` raises the page size of `tools/list` from fifteen to a hundred. Six modules now
offer more than forty tools between them, and a client that does not follow the cursor was seeing
a third of them and concluding the rest did not exist.
