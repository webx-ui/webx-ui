# webx-ui/module-pages

The pages of the site as a tree: the home page is its root, a page's address is the slugs of its
ancestors and its own, its content is made of blocks, it has a draft, publications and a bin. The
section «Pages» of the panel and the MCP tools `pages_*` edit it. Almost nothing here is written
from scratch: the tree is `webx-ui/nested-set`, the address `webx-ui/routing`, the content
`webx-ui/module-blocks`, the draft and versions `webx-ui/module-admin`, the languages
`webx-ui/localization` — read their guides when the question is about one of those.

## What it owns

- **Table** `pages` (`WebxUi\Pages\Models\Page`); the migration also creates the home page. `title`
  and `slug` are translatable: a page without a slug in a language has no address there.
- **Address type** `page` in the routing registry, formatter `TreePath`, `OnConflict::Fail` — a
  taken address is an error on the slug field, never a quiet `about-2`. Moving a branch rewrites
  every address below and leaves a 301 alias on each old one.
- **Public view** `config('webx-pages.view')`, default `pages.show`, receiving `$page`; until the
  site has that view the package prints `webx-pages::show`, a bare document.
- **Panel screen** `pages.form` (tabs `content`, `settings`, `seo`, `history`); API under
  `/api/cms/pages`; permissions `pages.view`, `pages.manage`.
- **MCP** tools `pages_tree`, `pages_get`, `pages_create`, `pages_update`, `pages_move`,
  `pages_publish`, `pages_unpublish`, `pages_delete`, `pages_restore`, `pages_discard`, `pages_versions`,
  `pages_version_restore`, `pages_purge`; resource `pages://sitemap`;
  prompt `build_page`. Scopes `pages:read`, `pages:write`.
- Also registered: a link source (pages in every link picker), an audit content source when
  `webx-ui/module-audit` is installed, demo content (`resources/demo`).

## Change it without forking

| You want                                  | Do this                                                                                       |
| ----------------------------------------- | --------------------------------------------------------------------------------------------- |
| Pages inside the site's header and footer | `'layout' => 'layout'` in `config/webx-pages.php` (`<x-layout>`); `webx:panel --sync` sets it |
| Different markup around the content       | write `resources/views/pages/show.blade.php`, or point `WEBX_PAGES_VIEW` at another view      |
| Change the package's own views            | `php artisan vendor:publish --tag=webx-pages-views`, keep only the files you change           |
| No visible breadcrumbs                    | `WEBX_PAGES_BREADCRUMBS=false` (the BreadcrumbList in `<head>` stays, it is module-seo's)     |
| A field or tab in the editor              | a patch: `Screens::extend('pages.form', [...])` in `AppServiceProvider::boot()`               |
| Other words in the panel                  | `php artisan vendor:publish --tag=webx-pages-lang`                                            |
| What a page looks like inside             | blocks — a block type in `webx-ui/module-blocks`, not a template here                         |

The layout component's contract is two slots: `head` and the default one for the content. It
must also print `@stack('head')` beside `{{ $head }}`, or what block types push never reaches
the page; `php artisan webx:doctor` says so when it is missing.

A screen patch addresses nodes by their `id` (`content`, `blocks`, `settings`, `naming`, `title`,
`slug`, `place`, `danger`, `seo`, `history`, `versions`); a patch whose target is gone throws at
boot instead of failing quietly. Values the screen does not name are dropped on save.

## Do not

- Do not edit anything in `vendor/webx-ui/module-pages`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not add a route for `/` in `routes/web.php`: the home page is the root of the tree and owns
  `/`. A route there takes the address from it for good.
- Do not create a second root or move, delete or give an address to the home page — all three are
  refused. `$page->capabilities()` says what a node allows.
- Do not write blocks through `pages_update`: content goes through `blocks_edit_content`, which
  changes one node and leaves the rest of the page alone. A `blocks` key there is refused.
- Do not write without the `revision` pages_get gave you: `pages_update` refuses a write
  with none and refuses a stale one. Read again and redo the change; do not retry blindly.
  `force: true` writes without one and is for a script that means to overwrite, not for an
  agent working beside people. `pages_get` names in `being_edited_by` who has the page open
  in the panel right now — tell your user before writing under them. Their editor merges your
  write with theirs field by field, and a draft written over by somebody else is kept: the
  panel's History lists it under Drafts, `pages_versions` under `drafts` (kind `overwritten`),
  and `pages_version_restore` with `draft` puts it back into the draft.
- Do not publish, unpublish, discard, restore a version, move or delete a page somebody has
  open without the `revision`: those tools act on whatever the draft holds now, which may be an
  edit you never read. While `being_edited_by` is not empty they refuse a call without one and
  name who has it open; a stale one is always refused. `force: true` goes ahead regardless.
- A page has one revision: the one `pages_get` returns is the one `blocks_get_content` returns,
  and either may be sent to `pages_update` or `blocks_edit_content`.
- Do not delete rows with SQL: deleting a page bins its whole branch and releases its addresses,
  and `restoreBranch()` brings back exactly that branch. A raw delete leaves orphans in the tree
  and in the routing registry.
- Do not `forceDelete()` a page by hand to delete it for good: `Page::purgeBranch()` (API
  `DELETE /pages/{id}/purge`, `DELETE /pages/bin`, MCP `pages_purge`) takes only a page in the bin,
  and removes its whole branch node by node, deepest first, so each page's `deleted` event takes
  its addresses, the former ones kept for a restore, its SEO card and its history. A bulk delete
  of the subtree skips those events and leaves all of it behind.
- In a partial `pages_update`, a translated field changes only in the languages you name:
  `{"slug": {"de": "…"}}` leaves the others, `null` empties one. A language the site is not
  published in is refused, dry run included.
- Do not print `<title>` in a site's copy of the view: with an empty SEO card the page names
  itself (`seoFallback()` — its title through the title template, the site's name on the home
  page). `@webxSeo($page)` prints it.

## Check your work

- `php artisan webx:doctor` — what is misconfigured, the layout and its `@stack('head')` among it.
- Open the page on the site after publishing it. A page that was never published is a 404 to
  everybody; a draft is seen through the preview link the panel's editor gives.
- With MCP: `pages_tree`, then `pages_get` by address (`"/"` is the home page); every tool that
  changes something takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory — the PHP API: publishing, deleting, the editor, the view.
- Guide: https://webx-ui.github.io/webx-ui/guide/pages
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_PAGES.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
