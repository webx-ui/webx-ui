# Pages

`@webx-ui/module-pages` is the section where the site's pages are edited, and
`webx-ui/module-pages` on the server is the tree behind it. This page is both, because neither is
useful alone.

Very little is written in either half, and that is the point. The tree is `webx-ui/nested-set`,
the address is the registry of [`webx-ui/routing`](/guide/routing), the content is
[blocks](/guide/blocks), the draft and the history are `module-admin`, what a page says about
itself is [`module-seo`](/guide/seo), the languages are `webx-ui/localization`. The module is the
first entity that puts all of them together on something real — and therefore the first honest
test of each.

## Install

```bash
pnpm add @webx-ui/module-pages
composer require webx-ui/module-pages
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { pages } from '@webx-ui/module-pages'
import '@webx-ui/module-pages/style.css'

createAdmin({
  modules: [pages()],
})
```

The section appears in the Content group, first in it, once both halves are there. Permissions:
`pages.view` opens the section and the picker a link field would use, `pages.manage` writes. The
migration creates the `pages` table and the home page in it.

A site that installs this module gives `/` to it and deletes its own route for the front page —
including the `welcome` one from the Laravel skeleton. As long as the application answers at `/`
itself, the registry holds that address reserved and the home page never gets a row in it.

## The home page is the root

The tree has exactly one root, it is the home page, and it is always there. Its slug is empty in
every language, so the path it formats to is `''` — which is what the registry calls the front
page — and its child `about` answers at `/about` rather than `/home/about`.

Its content is edited like any other page's. Its structure is not: it cannot be moved, deleted or
given an address, and a second root is refused. The panel is told which of those apply by the
node's capabilities rather than by a "this is the root" flag, so the next page that has to stay
put — a search page, a 404 — reuses the rule instead of adding a check:

```php
$page->capabilities();          // ['move' => true, 'delete' => true, 'address' => true]
Page::home()->capabilities();   // all three false
```

## Addresses

A page's address is the slugs of its ancestors and then its own, which makes moving a page under
another one the same gesture as changing its address:

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
leaves a 301 alias on each of the old ones — the registry does that by itself, and both the panel
and the agent tools say how many pages it happened to.

`title` and `slug` are translatable, and the address is per language. A page with no slug in a
language has no address in that language and does not open there. That is deliberate: an address
built from another language's slugs would serve English words in front of content nobody
translated.

## Drafts, publishing, history

Every save from the panel is a save into the draft; the site keeps showing what it showed until
somebody publishes:

```php
$page->saveDraft(['title' => ['en' => 'About'], 'blocks' => [...]]);
$page->status();      // 'draft' — never published
$page->publish();     // now the site shows it
$page->status();      // 'published', or 'modified' once there is a draft again
$page->unpublish();
```

Three states and no fourth: never published · on the site · on the site with edits waiting. A page
that was never published is a 404 to everybody, and the same page under a preview token of
`module-blocks` is shown as it will be — that decision belongs to the handler, not to the
registry, which is why unpublishing does not release the address.

The history is the publications. The autosaves the editor writes every couple of minutes are
insurance, not history: a ring of unnumbered copies of the draft, and a list that mixed them in
would be a list nobody reads. Restoring an old version puts it in the draft rather than on the
site — publishing it is the same separate step it always is.

## Deleting takes the branch

```php
$about->delete();          // $about and everything under it
$about->restoreBranch();   // exactly what went down with it, and nothing else
```

One node at a time rather than one statement, because it is the delete event that releases an
address: a branch turned off in bulk would leave every address in it held by a page nobody can
see. Which page's deletion trashed a node is kept in `trashed_with`, so a restore does not bring
back something that was deleted earlier and separately.

Addresses come back with the pages — and if one was taken while the page was in the bin, the
restore is refused. A page quietly restored to a different address is worse than one that says
the place is occupied.

## The list

One level of the tree at a time. The home page is pinned above the list and its children are the
level: every page of the site is inside it, so drawing it as a branch would give every row a step
of indentation that says nothing.

- **Children arrive when a branch is opened** — a catalogue of a few hundred pages is never
  fetched whole.
- **Searching puts the tree away** and answers with a flat list of matches, each with its address
  underneath: a branch drawn for the sake of one match deep inside it tells the reader nothing.
  The box looks in every language the site has, not in the one the panel is open in — the list
  shows the title a page carries, so a page named in English alone is on the screen of a Russian
  panel and is found by that English name.
- **The bin is a filter, not a section.** It lists the pages somebody deleted; what went down with
  a page comes back with it.
- **Two ways to move a page:** drag it, or use "Move…" and pick the page it goes inside — which is
  the one that works on a touch screen and in a big catalogue.

On a phone the section stops pretending to be a tree. Cards below a table have neither indentation
to read nor a chevron to open, so at that width it asks the server for a flat list and prints each
page's address under its name — which is what identifies a page anyway.

## The editor

Four tabs: Content · Settings · SEO · History. The form is a [described screen](/guide/screens),
`pages.form`, registered by the package, so a module or a project adds to it with a patch rather
than a fork — the SEO card arrives that way, from `module-seo` and not from the site:

```php
app(ScreenRegistry::class)->extend('pages.form', [
    ['op' => 'add', 'target' => 'seo', 'node' => [...]],
]);
```

What a save carries is decided by that tree and checked by `ScreenValues`, so a key the screen
does not name is dropped and a refused value lands under the field it belongs to.

The panel sends back the `revision` it read the page at — a short hash of the content, because a
draft has no version number of its own — and a save whose revision is no longer the current one is
answered with a `409` carrying the page as it now is, instead of being written over whoever saved
in between. Two writers who saved the same thing did not conflict, and the check says so. The same
guard covers an agent, which is the case it was really written for.

```
GET    /api/cms/pages                            a level of the tree, search, filters, the bin
POST   /api/cms/pages                            a new page under a parent
GET    /api/cms/pages/{id}                       values, the trail, a preview link, the revision
PUT    /api/cms/pages/{id}                       the draft: { values, revision }
POST   /api/cms/pages/{id}/move                  { target, zone }
POST   /api/cms/pages/{id}/publish               · /unpublish · /duplicate
DELETE /api/cms/pages/{id}                       to the bin, with the branch · POST /restore
GET    /api/cms/pages/{id}/versions              the publications, newest first
POST   /api/cms/pages/{id}/versions/{n}/restore  an old one becomes the draft
```

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

## For an agent: MCP

The section is also a set of tools. With `webx-ui/mcp` installed (it comes with this module), the
panel serves one MCP server at `/api/cms/mcp`:

Passport comes with the panel, and a site switches it on once:

```bash
php artisan vendor:publish --tag=passport-migrations && php artisan migrate
php artisan passport:keys
```

Somebody then connects their own agent to it: they paste that address into Claude, ChatGPT or
`claude mcp add --transport http webx <address>`, the client sends them to the panel to sign in
and agree, and it leaves with a token of theirs. The agent acts as that administrator. On the
machine the site runs on, `php artisan mcp:start webx` is the same server over stdio, trusted the
way tinker is.

| Tool            | What it does                                                                            |
| --------------- | --------------------------------------------------------------------------------------- |
| `pages_tree`    | The tree, one level of it, a search, or the bin: addresses, status, who touched it last |
| `pages_get`     | One page in full: the trail, the values, the revision, a preview link                   |
| `pages_create`  | A new page under a parent, as a draft                                                   |
| `pages_update`  | The values into the draft, guarded by the revision                                      |
| `pages_move`    | Somewhere else in the tree, and it says how many addresses changed                      |
| `pages_publish` | The draft onto the site · `pages_unpublish` takes it off                                |
| `pages_delete`  | To the bin with its branch · `pages_restore` brings the branch back                     |

Every tool that changes something accepts `dry_run: true` and then reports what it would do
without doing it. A page is named by its id or by its address — `"/catalog/shoes"`, and `"/"` for
the home page — because that is what a site is talked about in, and what the sitemap hands over.
Text fields answer with every language at once: an agent that got one title has no way of knowing
whether the others exist.

**Content does not travel through these tools.** Blocks are `blocks_edit_content`, which names the
node it changes and leaves the rest of the page alone; sending a `blocks` key to `pages_update` is
refused with that sentence rather than ignored. There is one way to change the content of a page,
and a second one would be the way that overwrites twenty blocks to fix a heading.

Before writing, an agent reads `pages://sitemap`: every page of the site nested the way it is
nested, with its address in each language, its status and what may be done to it. One prompt,
`build_page`, packages the loop — read the map and the block catalogue, create the page, fill it
with `blocks_edit_content`, look at the preview, write the SEO card, and leave it as a draft for a
person.

## Config

`config/webx-pages.php`:

| Key    | Default      | What it is                                                                 |
| ------ | ------------ | -------------------------------------------------------------------------- |
| `view` | `pages.show` | The view the handler prints a page with; the package's own until it exists |

## What is deferred

- **Permissions** beyond `pages.view` and `pages.manage`: publishing and deleting as rights of
  their own arrive with the wider conversation about roles.
- **`sitemap.xml`** belongs to `module-seo`, which needs every kind of entity and not only pages.
- **Scheduled publishing** needs a scheduler running on the site.
- A diff between two versions, bulk operations over a selection, copying a branch whole.
- The site's menu — a candidate for its own package, not a field of a page.
