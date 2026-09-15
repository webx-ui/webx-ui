# webx-ui/module-blocks

**Access to this section is access to deployment.** A block type is Blade, and Blade is PHP:
whoever can save a block can run anything the application can. Give the permission to the people
you would give a shell to, and turn `webx-blocks.editing` off on a site whose types arrive by
import.

The block constructor for the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel. A block
type is made entirely in the panel — its fields, its Blade template, its styles and its script —
and stored in the database; an entity's content is a tree of such blocks, and this package prints
it. Pages, articles and products add one trait and know nothing else about blocks.

Status: the rendering half and the bundles of styles and scripts (this README). The preview,
the panel section and the MCP tools follow; the plan is
[`docs/architecture/WEBX_UI_MODULE_BLOCKS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_BLOCKS.md).

## Requirements

- PHP 8.3+
- Laravel 13

## Install

```bash
composer require webx-ui/module-blocks
php artisan migrate
```

Three tables: `blocks` (the type), `block_versions` (one immutable snapshot per save) and
`block_bundles` (the glued CSS and JS of every set of types that has appeared on a page together).

## An entity with blocks

```php
Schema::table('pages', function (Blueprint $table) {
    $table->blocks();   // `blocks` json — the tree the site prints — and `draft` json
});

class Page extends Model
{
    use HasBlocks;
}

$page->blocks;          // [{ key, type, values }, …]
$page->renderBlocks();  // HtmlString
$page->blockTypes();    // ['hero', 'section', 'text'] — nested ones included
```

Or, from anywhere a site renders content of its own:

```php
{!! Blocks::render($blocks, $page) !!}
```

A node is `{ key, type, values }`: `key` identifies the instance and survives a drag, `type` is a
block's slug, `values` are keyed by the ids of the block's fields. A container block holds other
blocks in one of its values — a list of nodes of the same shape.

## A block type

```php
$block = Block::create(['slug' => 'hero', 'title' => 'Hero', 'group' => 'content']);

$block->saveVersion([
    'schema'   => [['id' => 'title', 'type' => 'wx-input', 'label' => 'Title']],
    'template' => '<section data-wx-block="hero" class="b-hero"><h1>{{ $title }}</h1></section>',
    'styles'   => '.b-hero { padding: var(--wx-space-32) }',
    'sample'   => ['title' => 'Welcome'],
]);

$block->publish();
```

Every `saveVersion()` writes a numbered snapshot and points the draft at it; only what changed has
to be sent. `publish()` renders the version on its sample values first and refuses, with the
template's line, when that throws — one typo would otherwise take down every page the block stands
on. The published version is what the site prints; a draft on top of it changes nothing until it
is published too.

Inside the template:

- the schema's fields are variables — `$title` — filled from the values and `null` where the
  content has nothing, so a field added later does not break the pages written before it. A
  variable the schema does not declare is what the publish check refuses;
- `$block` — `key`, `type`, `version`, and `value('project-name')` for a field whose id is not a
  variable name;
- `$entity` — the model being rendered, or `null` when there is none (the sample check, a
  controller rendering blocks on their own): write `$entity?->title`;
- `@blocks('content')` prints the blocks held in that field, one level deeper, up to
  `webx-blocks.max_depth` levels.

Put `data-wx-block="{slug}"` on the root element: the script runtime and the panel find the
block by it.

## What happens when a block fails

Every block renders inside its own try/catch. On the live site a failure goes to the exception
handler and the block is left out, the rest of the page intact. In the preview it is a notice in
the block's place with the message and the template's line. A block whose type is no longer
published is left out and noted in the log.

## Styles and scripts

```blade
<head>
    @webxBlocks            {{-- the stylesheet and the script of every block the page rendered --}}
</head>
```

The types on a page and their versions, in `sort` then `slug` order, make a hash; the hash names
a row of `block_bundles` with the glued CSS and JS, written the first time that set is seen and
served by `/blocks/{hash}.css` and `/blocks/{hash}.js` with a year-long immutable cache. A page
pulls only what stands on it, pages with the same set share the file, and publishing a version
changes the hash only where the block stands. A page without blocks prints nothing.

The directive is evaluated where it stands — with `@extends` and with components the content
renders before the layout, so the head knows what is below it. `@webxBlocks('styles')` and
`@webxBlocks('scripts')` split the two tags between the head and the end of the body; a set that
weighs no more than `webx-blocks.bundles.inline_below` bytes is printed inline instead.

A block's `script` is the body of an initialiser, run once per instance:

```js
// the script field of the block "hero"
const Swiper = await webx.use('swiper') // whatever the site's own bundle provided
new Swiper(el, values.options)
```

The runtime rides in the bundle. It finds every `[data-wx-block="hero"]` and calls the function
with the element and the values it carries in `data-wx-values` (JSON, `{}` when absent —
`data-wx-values="{{ json_encode($block->values) }}"` in the template); `webx.mount(root)` picks up
what is new inside a subtree, which is what the panel calls after replacing a block. The bridge
to the site's build is `webx.provide('swiper', Swiper)` on the site's side and `await
webx.use('swiper')` in the block: `use` waits, so the order the two files load in does not
matter — but `webx` has to exist when the site calls `provide`. A site that needs it before its
own entry prints `@webxBlocks('runtime')` in the head first; the copy inside the bundle then
steps aside.

Housekeeping: `webx:blocks:bundles --prune` drops the bundles glued from versions no longer
published, `--warm` writes the bundle of every entity of the models listed in
`webx-blocks.entities` ahead of the first visitor, and `webx:blocks:clear` forgets the cached
types and the compiled templates.

## The registry

```php
BlockTypes::all();          // enabled types with a published version, in sort order — the picker
BlockTypes::find('hero');   // the published version, disabled or not — what the site renders
BlockTypes::draft('hero');  // the draft, or the published one when there is none — the preview
```

Cached (`webx-blocks.cache`) and forgotten whenever a block or a version is saved, published or
deleted.

## Config

`php artisan vendor:publish --tag=webx-blocks-config` — groups, editing, nesting depth, where the
compiled templates go, cache, the bundles' path prefix and inline threshold, the entities to warm.

## License

MIT.
