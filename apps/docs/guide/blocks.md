# Blocks

`@webx-ui/module-blocks` is the section of the panel where a block type is made entirely — its
fields, its Blade template, its styles and its script — and `wx-blocks`, the field that builds an
entity's content out of such blocks. Its other half, `webx-ui/module-blocks` on the server, stores
the types, renders them into a page and serves the preview. This page is both, because neither is
useful alone.

**Access to the section is access to deployment.** A block type is Blade, and Blade is PHP:
whoever can save a block can run anything the application can. `blocks.manage` is given the way a
shell is given, and a production site can turn editing off altogether and bring its types in by
import.

## Install

```bash
pnpm add @webx-ui/module-blocks
composer require webx-ui/module-blocks
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { blocks } from '@webx-ui/module-blocks'
import '@webx-ui/module-blocks/style.css'

createAdmin({
  modules: [blocks()],
})
```

The section appears in the System group, above SEO, once both halves are there. Permissions: `blocks.view` opens
the section and the picker, `blocks.manage` writes. `php artisan vendor:publish
--tag=webx-blocks-config` publishes `config/webx-blocks.php`; the keys are listed at the end.

## An entity with blocks

The tree of blocks is a JSON column on the entity, and the draft the preview shows is the `draft`
column the versions mechanism of `module-admin` uses. Two macros add both:

```php
Schema::create('pages', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->blocks();   // json `blocks`, nullable
    $table->draft();    // json `draft` + `published_at`, from module-admin
    $table->timestamps();
});
```

```php
use WebxUi\Admin\Versions\{HasDraft, HasVersions};
use WebxUi\Blocks\HasBlocks;

class Page extends Model
{
    use HasBlocks;
    use HasDraft;
    use HasVersions;
}
```

`HasBlocks` is the whole integration on the model side. `$page->blocks` is the tree,
`$page->renderBlocks()` the HTML, `$page->blockTypes()` the slugs it uses. A view prints it where
the content goes:

```blade
{{-- resources/views/page.blade.php --}}
@extends('layouts.app')

@section('content')
    {!! $page->renderBlocks() !!}
@endsection
```

and the layout prints the styles and scripts of whatever the page rendered:

```blade
<head>
    …
    @webxBlocks
</head>
```

`@webxBlocks` is evaluated where it stands, after the content — with `@extends` and with
components the layout runs last — so it prints exactly the bundle of the types on this page and
nothing on a page without blocks. `@webxBlocks('styles')` and `@webxBlocks('scripts')` split it
when the scripts belong before `</body>`.

Name the models that hold blocks in the config, so the section knows where a type stands and an
agent knows what it may address:

```php
// config/webx-blocks.php
'entities' => [App\Models\Page::class],
```

A page that needs no editor stays an ordinary Laravel page — its own route, its own controller —
and may still call `Blocks::render($tree)` wherever it likes inside its view.

## The `wx-blocks` node

An entity's screen puts the constructor on a tab as one node:

```json
{
  "id": "content",
  "type": "wx-blocks",
  "name": "blocks",
  "label": "Content"
}
```

Nothing selected, the tab is two columns: the tree of blocks and the whole page in preview, at
desktop width. A block selected, it is three: the tree, the block's fields as a form, and the page
as a phone at one to one — the width that breaks first and is still readable. Desktop and tablet
open full-screen from a button; `Esc` comes back.

The preview is the entity's own `/_preview/…` address, and only the screen that hosts the node
knows which entity it is editing. It hands the address in:

```ts
import { provideBlocksPreview } from '@webx-ui/module-blocks'

provideBlocksPreview({ url: previewUrl, reload: reloadCounter })
```

`url` is what `Preview::url($entity)` returned — signed, an hour long, one entity; bump `reload`
after an autosave so the frame shows the new draft. Without it the field is a tree with a form,
which is all a screen outside the panel can be. After a field changes, the constructor asks the
server to draw that one block and swaps it into the frame between the marker comments, so the
page does not reload on every keystroke.

Every row in the tree carries three buttons: an eye, a copy and a bin. The eye switches that
block off — it stays in the content and is edited the same way, and the site stops drawing it,
along with everything nested inside it. Their own switches keep their values, so a container
turned back on is exactly what it was. A hidden block is dimmed in the tree, carries an
eye-off beside its name, and is absent from the preview: a preview that still showed it would
not say which block is the switched-off one.

Inside a block's own schema the same node makes the type a container:

```json
{
  "id": "content",
  "type": "wx-blocks",
  "label": "Content",
  "props": { "allow": ["text", "gallery", "columns"], "max": 8 }
}
```

Its value is a list of nodes of the same shape as the page's own, and the template prints it with
`@blocks('content')`. Nesting stops at `max_depth` (five by default): deeper than that is not a
constructor but layout by mouse.

## A block type

Five things, all made in the section:

| Field      | What it is                                                                  |
| ---------- | --------------------------------------------------------------------------- |
| `schema`   | The fields an editor fills in — [screen nodes](/guide/screens), same format |
| `template` | Blade that prints the block from those fields                               |
| `styles`   | CSS for this block alone                                                    |
| `script`   | Optional: the body of an initialiser, run once per block on the page        |
| `sample`   | A value for every field                                                     |

Around them, the settings: `slug` (also the CSS prefix), `title` and `description` for the picker,
`group` — one of `webx-blocks.groups`, the sections of the picker — `allow` (what may go inside;
null means not a container), `allowed_in` (where it may go; type slugs, `root` for the page
itself, null for anywhere), `max_per_entity`, `is_enabled`.

**The schema is screen nodes.** `{ "id": "title", "type": "wx-input", "label": "Title" }` — the
`id` is the variable the template gets and the key in the values, so it is snake_case. Layout
nodes (`wx-card`, `wx-tabs`, `wx-row`) group fields; the values stay flat. Translatable labels
work with the same `trans::` marker as everywhere on a screen.

**The template is Blade** with the fields as variables, plus `$block` (`key`, `type`, `version`,
`depth`, `value('name', default)`) and `$entity`, the record the block stands on. The root element
carries `data-wx-block="{slug}"`: the runtime finds the block by it and the panel highlights it by
it. Every block renders in its own `try`/`catch` — on the site a failure goes to the log and leaves
a gap; in the preview it is a notice with the line.

A variable holds what the field type makes of the stored value, the same way
[a screen's values](/guide/screens) are read for the site: `wx-media` stores
`{ path, alt, title }` and the template also gets `url`, worked out when the block is printed, so
a library that moves to another disk rewrites no page. A value whose type nobody registered on the
server — `wx-blocks`, a field of the project's own — arrives as it is stored. `$block->values`
holds the same map, which is what a template hands its script in `data-wx-values`.

The way in is the same walk. What a save keeps is what the field type makes of what was sent —
the editor's save and `blocks_edit_content` alike — so a type that cleans what it is given cleans
it here too, rather than on screens only. The same two things pass through untouched: a value
whose key the schema no longer names, and a value of a type nobody registered. A nested tree is
walked as blocks, not handed to a field type: the blocks inside it are kept by their own schemas.

**A gallery is `wx-gallery`, not a repeater of pictures.** `wx-gallery` and `wx-files` hold a list
of those same values, in an order somebody dragged them into, and the editor picks ten of them in
one trip to the library rather than ten times over. `wx-repeater` is still the answer for a list
whose items have fields of their own beside the picture.

```json
{ "id": "shots", "type": "wx-gallery", "label": "Photographs", "props": { "max": 12 } }
```

```blade
@foreach ($shots as $shot)
  <img src="{{ $shot['url'] }}" alt="{{ $shot['alt'] }}"
       width="{{ $shot['width'] }}" height="{{ $shot['height'] }}">
@endforeach
```

Each item arrives resolved with everything the library knows: `url` and `thumb`, `name`,
`extension` and `mime`, `size`, and `width` and `height` for a picture. The whole set rather than
the address alone, because a template has nothing to ask the library with — `width` and `height`
are what keep the page from jumping, and `size` with `extension` are what a link to a document is
labelled with. A file that has since been deleted comes back with `url: null` instead of breaking
the page.

**The styles start with `.b-{slug}`**, in BEM: `.b-hero__title`, `.b-hero--wide`. Width decisions
are container queries, because the block does not know whether it is the page or a third of it.
Saving reports what leaks — a selector outside the prefix, a bare element selector, `@media`, a
missing `data-wx-block` — as warnings under the editor, never as a refusal.

**The script is a body**, not a program:

```js
// the field
const Swiper = await webx.use('swiper')
new Swiper(el.querySelector('.b-gallery__track'), values.autoplay ? { autoplay: true } : {})
```

goes into the bundle as `webx.block('gallery', async (el, values) => { … })`, and a thirty-line
runtime calls it on every `[data-wx-block="gallery"]` on the page. `webx.provide('swiper', Swiper)`
in the site's own bundle is how a block reaches what the site already ships; `webx-blocks.provides`
lists those names, so the editor shows them beside the script and an agent asks for what exists.
`webx.mount(root)` runs the initialisers again on a subtree — the panel uses it after swapping a
block in the preview.

**The sample is not decoration.** It is the thumbnail an editor picks the block by, the values the
publish check runs on, and the clearest documentation of the data shape.

## Versions and publishing

Every save writes a version; nothing is edited in place. A type has two pointers: the `draft` the
editor works on and the `published` version the site prints. Publishing is its own step, and it
can be refused: the template is compiled and rendered on the sample **and on the values of every
page the block already stands on**. One typo would otherwise take down every page with the block,
and the first to know would be a visitor. A refusal shows the line and, when a page rather than
the sample broke, which page.

The history lists every version with its author and source — `panel`, `mcp` or `import` — and
that is an audit log as much as an undo buffer: a block is code. Restoring an old version makes it
the draft; publishing it is the same step as any other.

`webx-blocks.editing = false` makes the section read-only whatever the permission says: on a site
whose types arrive by import, nobody needs to run Blade from a form.

## Preview

```php
use WebxUi\Blocks\Facades\Preview;

Preview::url($page, adminId: $admin->id);
// https://example.test/_preview/page/12?token=…
```

`/_preview/{type}/{id}` does what the [address registry](/guide/routing) does for the real
address — finds the route type, loads the entity, hands both to the type's handler — and the
handler answers with the same view it answers the site with. What differs: the entity carries its
draft over its columns (`withDraft()`), the block types render at their drafts, every block is
wrapped in the marker comments the panel finds it by, and the response is `no-store` and
`noindex`. The token is signed with the application key, lives `preview.ttl` minutes and opens one
entity; a bad or expired one is a 403.

A handler tells a preview from a visit with `PreviewGrant::of($request)` — and that is where an
unpublished entity is a 404 to everybody else. The route runs through `preview.middleware` (`web`
by default) so the site's locale and session handling apply to the preview as to the page.

## The block editor's stage

The editor draws the block a type is being written for on a page of the site — the site's layout,
its header, footer, fonts and base styles — because a block judged against the browser's defaults
looks like a draft, and one judged on the site looks like what it will be.

`/_preview/block-stage` is that page: the component `webx-blocks.layout` names, with an empty
pair of markers for the content and an empty `<style>` in the head. The panel loads it once and
then, on every change of the template, the styles or the sample, swaps the block in between the
markers and its styles into the `<style>`, so the header and footer do not redraw under the
editor's typing. A new script is the one change that reloads the page: a script registered in a
page cannot be taken back. Links and forms of the site are inert there.

```php
// config/webx-blocks.php
'layout' => 'layout',   // <x-layout>, the same one the pages stand in
```

Empty prints `webx-blocks::standalone`, a bare document. `php artisan webx:panel --sync` sets the
key the same way it does for pages and the blog, and `webx:doctor` warns while it is empty. The
route runs through `preview.middleware` and needs an editor's session with `blocks.view`.

## Styles and scripts on the site

The types on a page are known from its tree, so the page gets one stylesheet and one script, named
by the hash of the types and versions on it: `/blocks/{hash}.css`, cached forever. Two hundred
pages share a couple of dozen bundles; publishing a version changes the hash only where the block
stands. Small sets can be inlined instead (`bundles.inline_below`).

```bash
php artisan webx:blocks:bundles --prune   # drop bundles of versions no longer published
php artisan webx:blocks:bundles --warm    # build the bundle of every entity in `entities`
php artisan webx:blocks:clear             # forget the cached registry of types
```

## Export and import

The one real cost of keeping types in the database is that a block does not travel through git on
its own. Two commands close that:

```bash
php artisan webx:blocks:export             # resources/blocks/{slug}.json, one per published type
php artisan webx:blocks:export hero --draft --path=/tmp/blocks
php artisan webx:blocks:import             # back in, as drafts; a version only where content differs
php artisan webx:blocks:import --publish   # …and publish what passes the checks
php artisan webx:blocks:import --dry-run   # say what would change
```

A file is the row's settings and one version's content, flat, pretty-printed for a diff. Import
checks it by the rules the panel checks a save with, brings the row up to date, writes a version
only when the content differs from the one being edited — importing the same files twice writes
nothing — and, with `--publish`, runs the publish checks and publishes what passes; a type that
fails is left as a draft and the command says so with its exit code. Keep the export in the
repository as a seed for a fresh install and as the thing that gets reviewed; moving types from a
local site to a production one becomes two commands.

The two drift apart quietly, so export on the day you edit. A type changed in the panel and not
written back leaves the file describing the release before: the site is fine, because the site
reads the database — what breaks is the next fresh install, the next deploy that seeds from the
files, and the test run that builds a page out of them. That is a failure somewhere else
entirely, one release later, and it reads like a broken package rather than a stale file.

## For an agent: MCP

The section is also a set of tools. With `webx-ui/mcp` installed (it comes with the blocks
module), the panel serves one MCP server at `/api/cms/mcp`:

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

| Tool                  | What it does                                                                             |
| --------------------- | ---------------------------------------------------------------------------------------- |
| `blocks_list`         | The types: names, fields, where each may go, published or not, on how many pages         |
| `blocks_get`          | One type in full, at the current or a given version, with the warnings on it             |
| `blocks_create`       | A new type as a draft                                                                    |
| `blocks_update`       | Any settings and any of the five content fields; a version only when content differs     |
| `blocks_publish`      | Publish the draft — the same checks as the panel; `dry_run` runs them and moves nothing  |
| `blocks_render`       | Draw a type on values (the sample by default): HTML, styles, script, or the failing line |
| `blocks_get_content`  | An entity's blocks: the map with `outline`, one node with `key`, both trees by default   |
| `blocks_set_content`  | Replace the entity's draft with a tree of nodes; keys are kept or made                   |
| `blocks_edit_content` | Change one block at a time: `set`, `add`, `move`, `remove`, `hide`, `show`, by key       |
| `blocks_preview_url`  | A signed link to the entity's draft as the page it will be                               |

`render` and `preview_url` are what close the loop: without them an agent writes a template it
never sees, and the site gets the markup it imagined. Every tool that changes something accepts
`dry_run: true` and then reports what it would do. Publishing an entity is not offered: the agent
writes the draft, a person looks at the preview and publishes.

### Changing part of a page

Reading a page and writing it back is the expensive way to change one heading: the whole tree
goes both ways, and anything an editor did in between is quietly lost. `blocks_edit_content`
names the node instead, and everything else stays the object it already was:

```json
{
  "entity": "page",
  "id": 12,
  "revision": "8a41c0d2f7b3",
  "ops": [
    { "op": "set", "key": "b7f3", "values": { "title": "A new heading" }, "locale": "en" },
    { "op": "add", "type": "text", "parent": "b1a0", "after": "b7f3", "values": {} },
    { "op": "move", "key": "b9de", "before": "b7f3" },
    { "op": "remove", "key": "b2c1" },
    { "op": "hide", "key": "b4aa" }
  ]
}
```

- `set` merges field by field: a field nobody mentions keeps its value, and `locale` writes one
  language of a localized field rather than replacing the map with a string.
- `parent` omitted means the top level; `field` names the `wx-blocks` field when the parent block
  has more than one. `before` and `after` place the node among its siblings.
- `hide` and `show` switch one block off and back on. A hidden block stays in the content and
  keeps everything in it; the site simply does not draw it, nor anything nested inside it.
  `outline` reports `hidden: true` for those, which is the only way to tell from the content
  that a block is not on the page.
- `revision` is the one `blocks_get_content` returned. Send it and the edit is refused when the
  entity changed in between, instead of overwriting whoever changed it. `blocks_set_content` takes
  it too.

Start from the map rather than the page: `blocks_get_content` with `outline: true` answers with
the keys, types, nesting and a line of text each, and with `key` it answers with that one node in
full. The values of twenty blocks are not what you need to edit one.

Before writing, an agent reads the module's resources: `blocks://guidelines` (the house rules
above, as a page for a model), `blocks://catalog` (every type with its fields and sample, to reuse
rather than duplicate), `blocks://fields` (the node types a schema may use) and `blocks://site`
(the picker groups, what `webx.provide()` offers, the nesting limit, the entities). One prompt,
`design_block`, packages the loop: read, reuse or create, render on the sample and on nothing, fix,
report — and do not publish unless asked.

## Config

`config/webx-blocks.php`:

| Key                    | Default                          | What it is                                                       |
| ---------------------- | -------------------------------- | ---------------------------------------------------------------- |
| `groups`               | `content`, `layout`, `media`     | The sections of the picker, in order; labels from the dictionary |
| `editing`              | `true`                           | Off, and the section is read-only: types arrive by import        |
| `provides`             | `[]`                             | What the site's bundle hands to blocks through `webx.provide()`  |
| `max_depth`            | `5`                              | How deep containers may nest                                     |
| `compiled`             | next to the app's compiled views | Where compiled templates go, one file per version                |
| `cache`                | on, a day                        | The registry of published types                                  |
| `bundles.path`         | `blocks`                         | The prefix of `/{hash}.css` and `.js`                            |
| `bundles.inline_below` | `0`                              | Sets lighter than this many bytes are printed inline             |
| `entities`             | `[]`                             | The models that use `HasBlocks`                                  |
| `preview.path`         | `_preview`                       | The service prefix; closed to the address registry               |
| `preview.ttl`          | `60`                             | Minutes a preview link lives                                     |
| `preview.middleware`   | `['web']`                        | What the preview route runs through                              |
| `layout`               | empty                            | The site's layout the block editor's stage draws a block in      |

## What is deferred

- Dragging a block between two containers is "duplicate" plus "remove", not a drag.
- The line above the tree for what the view prints from the record's own fields, and the
  panel's menu folding to icons while the constructor is open, arrive with the first content
  module that needs them.
