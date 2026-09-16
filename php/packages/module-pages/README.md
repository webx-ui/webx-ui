# webx-ui/module-pages

Pages as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: a tree of
pages, addresses from the registry, content made of blocks, a draft and a history.

Very little of that is written here, and that is the point. The tree is `webx-ui/nested-set`, the
address is `webx-ui/routing`, the content is `webx-ui/module-blocks`, the draft and the versions
are `webx-ui/module-admin`, the languages are `webx-ui/localization`. This package is the first
entity that puts them together, and what it adds is the handful of rules that are about pages
rather than about any of them.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-auth`, `webx-ui/module-blocks`, `webx-ui/module-seo`,
  `webx-ui/routing`, `webx-ui/nested-set`, `webx-ui/localization`, `webx-ui/mcp`

## Install

```bash
composer require webx-ui/module-pages
php artisan migrate
```

The migration creates the `pages` table and the home page in it. Permissions: `pages.view`,
`pages.manage`.

## The home page is the root

The tree has exactly one root, it is the home page, and it is always there. Its slug is empty in
every language, so the path it formats to is `''` — which is what the registry calls the front
page — and its child `about` is at `/about` rather than `/home/about`.

Its content is edited like any other page's. Its structure is not: it cannot be moved, deleted or
given an address, and a second root is refused. The panel is told which of those apply through
the node's capabilities rather than through a "this is the root" flag, so the next page that has
to stay put reuses the rule:

```php
$page->capabilities();   // ['move' => true, 'delete' => true, 'address' => true]
Page::home()->capabilities();   // all three false
```

## Addresses

The address of a page is the slugs of its ancestors and then its own, which means moving a page
under another one is the same gesture as changing its address:

```php
RouteTypes::register(new RouteType(
    type: 'page',
    model: Page::class,
    formatter: TreePath::class,
    handler: PageHandler::class,
    onConflict: OnConflict::Fail,
));
```

`Fail` rather than `Suffix`: a page address is chosen deliberately, so a taken one is an error on
the slug field and not a quiet `about-2`. Moving a branch rewrites every address below it and
leaves a 301 alias on each of the old ones — the registry does that on its own, and the panel says
how many pages it happened to.

`title` and `slug` are translatable. A page with no slug in a language has no address in that
language and does not open there; that is deliberate, and better than serving one language's
address for another's content.

## Publishing

```php
$page->saveDraft(['title' => ['en' => 'About'], 'blocks' => [...]]);   // every save from the panel
$page->status();      // 'draft' — never published
$page->publish();     // now the site shows it
$page->status();      // 'published', or 'modified' once there is a draft again
$page->unpublish();
```

A page that was never published is a 404 to everybody, and the same page under a preview token of
`module-blocks` is shown as it will be. That decision is the handler's, not the registry's.

## Deleting

Deleting a page puts its whole branch in the bin, one node at a time, so that every address in it
is released:

```php
$about->delete();          // $about and everything under it
$about->restoreBranch();   // exactly what went down with it, and nothing else
```

Which page's deletion trashed a node is kept in `trashed_with`, so a restore does not bring back
something that was deleted earlier and separately. Addresses come back with the pages — and if one
was taken while the page was in the bin, the restore is refused rather than the page being quietly
moved somewhere else.

## The editor

The form is a described screen, `pages.form`, registered by this package: four tabs — the content
as a `wx-blocks` field, the settings, SEO, the history. A module or a project adds to it with a
patch rather than a fork:

```php
app(ScreenRegistry::class)->extend('pages.form', [
    ['op' => 'add', 'target' => 'seo', 'node' => [...]],
]);
```

What a save carries is decided by that tree and checked by `ScreenValues`, so a key the screen does
not name is dropped and a refused value lands under the field it belongs to. The panel sends back
the `revision` it read the page at — a short hash of the content — and a save whose revision is no
longer the current one is answered with a `409` carrying the page as it now is, rather than written
over whoever saved in between. Two writers who saved the same thing are not a conflict, and the
same check covers an agent.

```
GET    /api/cms/pages/{id}                       values, the trail, a preview link, the revision
PUT    /api/cms/pages/{id}                       the draft: { values, revision }
GET    /api/cms/pages/{id}/versions              the publications, newest first
POST   /api/cms/pages/{id}/versions/{n}/restore  an old one becomes the draft
```

## MCP

With [`webx-ui/mcp`](../mcp) — it comes with this package — the section is also a set of tools for
an agent: `pages_tree`, `pages_get`, `pages_create`, `pages_update`, `pages_move`, `pages_publish`,
`pages_unpublish`, `pages_delete`, `pages_restore`. The same doors the panel uses — `PageForm`
checks the values against the described screen, `Placement` decides where a page may go, the
`revision` refuses a stale write — with `mcp` as the source in the history; every tool that changes
something takes `dry_run: true`. A page is named by its id or by its address, `"/"` being the home
page, and text fields answer with every language at once.

Content is not written here. Blocks go through `blocks_edit_content`, which names the node it
changes and leaves the rest of the page alone; a `blocks` key sent to `pages_update` is refused
with that sentence rather than dropped. Scopes `pages:read` and `pages:write` are abilities of the
token `php artisan webx:mcp:token` issues. The resource `pages://sitemap` is the map to read first,
and the prompt `build_page` packages the loop of create, fill, preview, report.

## The view

The handler hands the page to `webx-pages.view`, which defaults to `pages.show`, as `$page`:

```blade
@php($content = $page->renderBlocks())
<!doctype html>
<html>
<head>
    @webxSeo($page)
    @webxBlocks
</head>
<body>{!! $content !!}</body>
</html>
```

Until the site has written that view, the package prints its own — the blocks, the SEO head and
nothing else — so a fresh installation serves a page rather than an error.

## Licence

MIT.
