# Layout regions

The header and the footer of a site are Blade in its repository — `components/header.blade.php`,
or written straight into the layout. The panel reaches only what they read: the items of
`menu('header')`, the phone number from `settings()`. Moving the logo, putting a strip with an
offer above the menu, building a footer of four columns is work for whoever edits the code and
deploys the site. A site put together in the panel, or by an agent, has no such door.

A **region** is that door: a named place of the layout whose content is a tree of blocks, edited
in the panel like the content of a page, with the markup from code printed for as long as the
region has nothing to show.

```blade
<x-webx-blocks::region name="header" fallback="components.header" />
<main>{{ $slot }}</main>
<x-webx-blocks::region name="footer" fallback="components.footer" />
```

Everything else stays where it was: the document, `<head>`, Vite, `@webxSeo`, `@webxBlocks`. A
region is part of [`webx-ui/module-blocks`](/guide/blocks); there is no package of its own.

**Why regions and not the whole layout.** The layout holds the seams of every module —
`@stack('head')`, the SEO tags, the bundles — and a mistake in it takes down every page of the
site, the one it would have to be fixed on included. A region breaks only itself (see
[When a block fails](#when-a-block-fails)) and differs from git by exactly its content, the way a
page does.

## Declaring a region

A region exists where the layout prints its tag, and it is declared in the configuration beside
it:

```php
// config/webx-blocks.php
'regions' => [
    'header' => [
        'title' => 'trans::webx-blocks::regions.header',
        'description' => 'Top of every page: logo, menu, the call to action.',
        'allow' => null,   // the block types allowed at its top level; null for any
        'max' => null,     // how many blocks at its top level; null for no limit
    ],
    'footer' => ['title' => 'trans::webx-blocks::regions.footer'],
],
```

Empty by default: whoever writes the layout is the one who knows its regions. The skeleton
[`webx-ui/site`](/guide/new-site) declares `header` and `footer` and prints both tags around its
own header and footer, so a fresh site looks exactly as it did before.

- **The row appears with the first save.** A declared region has no row in `block_regions` until
  somebody saves it — like a declared menu that was never saved.
- **An undeclared name prints its fallback** and writes a warning to the log: a typo in the name
  must not quietly become a second header nobody can edit. The panel shows only declared regions.
- **`title` and `description`** are translated with the `trans::` marker. `header` and `footer`
  are in the package's dictionary in all ten languages.
- **A type offered only in a region** says so in its `allowed_in`: `["region:header"]`. The root
  of a page is still `null` there, so the types a site already has lose nothing.
- A row whose name has left the configuration stays in the database and out of the panel;
  `php artisan webx:blocks:regions --prune` deletes such rows.

`php artisan webx:doctor` warns about a region that is declared and that no layout prints: what
the panel publishes there would never reach the site. The check reads the layout's source for
the tag with that name.

## The tag

`name` and `fallback` are the tag's own. Every other attribute is data of the call:

```blade
<x-webx-blocks::region name="header" fallback="components.header" :compact="true" />
```

- `fallback` is **the name of a view**, not of a component: `components.header` is
  `resources/views/components/header.blade.php`. It gets the other attributes as variables and
  as `$attributes`, as if it had been called `<x-header :compact="true" />`.
- The blocks of the region get the same attributes as `$region->data('compact')` — and as
  `$attributes` too, so a header view moved into a block keeps working. One `:compact="true"` on
  a landing page reaches both headers.
- Without `fallback`, an empty region prints nothing. That is legitimate for a strip above the
  header that is there only when somebody fills it.
- The tag prints **no element of its own** around the region. See the next section.

The fallback is printed when the region is not declared, has never been saved, is not published,
or has no visible block in what is published. "Bring back the header from code" is taking the
region off the site; nothing has to be deleted.

## The CSS boundary

The layout owns the frame, the block draws inside it. `position: sticky`, the width of the column,
the gap to the content, the shadow on scroll — all of that belongs to the layout, on an element
around the tag. A block in a region is an ordinary block type: classes under `.b-{slug}`,
container queries, no bare element selectors. What the two share is the site's CSS variables —
`--site-accent`, `--site-font`, whatever the layout declares — so that a header made of blocks and
the page under it stay one design, and neither side reaches into the other's rules.

The frame is usually a flex row (`<header class="site-header">` with the tag inside), and that
has one consequence for the block: **a root with `container-type: inline-size` must state its
width.** A query container has no intrinsic width, so as a flex item it shrinks to 0 px, and a
one-line menu lays itself out as a column of single words. In the flow of a page the same block
is fine, which is why it only shows up in a region. `width: 100%` on the root is enough; the
offered `menu` block and the demo header and footer carry it.

A region's styles and scripts are **its own bundle, printed by the tag** — a `<link
rel="stylesheet">` before the region and a `<script type="module">` after it when the bundle has
a script. `@webxBlocks` in `<head>` cannot carry them: the head is rendered before the layout
reaches the header. A stylesheet link in `<body>` is valid HTML, the browser holds rendering until
it arrives, and the file is the same immutable one by hash — for a header, one file for the whole
site, downloaded once.

## What a block sees in a region

Everything a block sees on a page — its values, `$block`, `$slot` — and two more:

| Name      | What it is                                                                                                                                  |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `$entity` | The entity of the page the visitor is on, when the address came from the [registry](/guide/routing); otherwise `null`                       |
| `$region` | `name`, `data($key, $default)` — the tag's attributes — and `path()`, the path of the request without its language; `null` outside a region |

Plus `$attributes`, as above. The rest is global and simply works, because a region is rendered
inside an ordinary request:

- the language — `app()->getLocale()`, and translated values of the blocks follow it;
- `menu('header')` — the active item is worked out on every request;
- `settings('contacts.phone')` — in the current language;
- `request()`, `auth()`, `@csrf`.

`$entity` is worth using: a «Subscribe to this rubric» that names `$entity->rubric` in the footer of an article, a
language switch in the header that needs this entity's address in another language. It is `null`
on a page with a route of its own, on a 404 and on the stage, so a template checks it — and so
does the sample. `$region` is `null` on a page, so a type used in both places writes
`$region?->data('compact')`.

### Why the HTML is not cached

Every item of that list is a function of the request: the active menu item, the language, the
CSRF token of a subscription form, «Sign in» or «Account». A cache by language would be a cache
by language _and_ address — one per page of the site — and still wrong for the form.

What is cached is the **tree** of the published region, by name and not by language: one tree
serves every language, and translated values are picked when it is rendered. Publishing, taking
off and restoring a version drop it; a draft saved on every keystroke does not touch it. Everything
else is already cached by whoever computes it: the templates of block types as a file per version,
`menu()` per menu and language, `settings()` in its own cache.

## When a block fails

**On the site, a region in which any block throws prints its fallback, whole.** On a page a
failing block leaves a gap, and that is the lesser evil there. In a header a gap where the menu
was is worse than the header from code: the visitor loses the navigation of the whole site. The
same goes for the region itself — a broken tree, no table yet. The exceptions go to the log as
usual; without a fallback the region prints nothing and a line goes to the log.

The preview does the opposite: the region is drawn with error plates where the blocks failed and
a strip saying «on the site the fallback will be here» — otherwise the editor would never see
what broke. Publishing renders the draft first and **refuses** a draft in which a block throws
(422, with the block and the line), so a published header that the site replaces with the
fallback does not happen by accident.

## Preview

A header cannot be judged apart from the page under it, so the preview of a region is **a real
page of the site** with the region's draft drawn in place of what is published:

```
/_preview/region/header?token=…&at=/about
```

- The page is resolved the way a visitor's request is — the same handler, the same entity, the
  same active menu item and language. `at` is a path of the site, the front page by default.
- The page under the region is the **published** one, and its blocks carry no markers: the only
  thing to select in this frame is the region.
- A path that is not in the address registry — a route of the site's own, a typo — draws the
  region on the stage (`/_preview/block-stage`: the same layout, empty content) with a strip
  saying so. A controller of the site is not run under a preview token: it may write, not only
  read.
- `no-store` and `noindex`, like every preview.

The stage of a block type and the preview of a page draw regions as published, without markers.
A region's draft is seen only in its own editor.

## The panel

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { blocks, regions } from '@webx-ui/module-blocks'
import '@webx-ui/module-blocks/style.css'

createAdmin({
  modules: [blocks(), regions()],
})
```

**Site regions** (`/regions`) is a second entry of the blocks package, next to the menus — not a
tab inside Blocks. That section is closed by `blocks.manage`, because saving a type is writing
Blade; a header is content, edited by whoever edits the pages. So a region has a permission of its
own, **`blocks.regions`**: it opens the section, saves, publishes and takes off. There is no
view/manage pair — there is nothing to look at without editing that the site does not already
show. Its holder picks from the published types without `blocks.view`: the catalogue answers them
too, and `?region=header` narrows it to what may stand at the top of that region.

- **The list** is a card per declared region: its title and description, its state — on the site
  with a version, a draft, or «the fallback from code» — and how many blocks are in it.
- **The editor** is the page editor without what a region does not have: the content (a
  `wx-blocks` field with the preview, and a picker of the page to preview on — a link to anything
  on the site, or an address; remembered per region), the history of publications with a restore
  into the draft, «Save draft», «Publish», «Take off the site», autosave and the revision guard.
  The screen is described in `regions.form.json`, so a site can add a tab by a patch.
- **An empty region** says what is on the site now — «the header from code
  (`components.header`)» — and offers the holder of `blocks.manage` **«Move the markup into a
  block»**: a type `site-header` whose template is the fallback view's source (the copy the site
  published first), offered only in this region, with one instance of it put into the draft. From
  there it is an ordinary block; nobody splits the markup into fields automatically.

«Where it is used» of a block type names the regions beside the pages, and the checks before a
type is published see the values stored in regions.

## For an agent: MCP

A region is an entity for the content tools, always — it does not have to be in
`webx-blocks.entities` — and its id is its name:

```json
{ "entity": "region", "id": "header", "outline": true }
```

- `blocks_get_content`, `blocks_set_content`, `blocks_edit_content` work as on a page: they write
  the draft, with `revision` against overwriting. A declared region that was never saved reads as
  empty; the first write makes its row.
- **`blocks_regions`** lists the declared regions: name, title, description, `allow`, state, the
  fallback view the layout named, the number of blocks and a preview URL.
- **`blocks_region_publish`** publishes the draft (`dry_run` says what would change — «replaces
  the header from code on the site»), **`blocks_region_unpublish`** brings back the fallback. Tools
  of their own rather than a general `publish`: the module that owns an entity owns its
  publication, like `pages_publish`.
- `blocks_preview_url` takes `entity: "region"` and an optional `at`.
- The administrator needs `blocks.regions`; the token, `blocks:write`.

The house rules of the blocks resource have a paragraph on regions: how a region differs from a
component, what `$region` and `$entity` hold, that the HTML is not cached so a template may call
`menu()`, and that a failing block sends the whole region to its fallback — so render with
`blocks_render` before publishing.

## Demo content

`php artisan webx:demo`, on a site that declares `header` and `footer`, adds two block types —
`demo-header` (the name of the site from `settings()`, or of the application; the header menu;
one button) and `demo-footer` (the footer menu in columns, a copyright line) — and **publishes**
both regions with them. The templates read the menus and the settings when they are rendered, so
a site with no menus module simply has no menu there. A region somebody has already saved is left
alone. Without declared regions the demo leaves the layout alone and says so in one line.

`--remove` deletes the rows of both regions, their history and the two types — and the site is
back to its header and footer from code.

## A menu as a block

`webx-ui/module-menu` offers a block type **Menu**, installed with
`php artisan webx:blocks:offered --install --module=menu` — see [Menus](/guide/menu#as-a-block).
It is what a header of blocks is usually made of, so that no site writes the loop over
`menu('header')` again.

## What is deferred

- **A region per entity** («the blog has its own header»): a field on the entity's screen that
  picks `header` or `header-blog`, the tag getting `:name` from it. It is added without breaking
  anything, when a site asks for it.
- **Regions made in the panel** — a `sidebar` the layout does not print. A region exists only
  where the layout prints its tag.
- **Exporting regions** from a local site to production — together with exporting the content of
  pages.
- **The whole layout in the database** — no; see the beginning of this page.
