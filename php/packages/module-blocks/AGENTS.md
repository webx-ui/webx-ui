# webx-ui/module-blocks

The block constructor: a block type — its fields, Blade template, styles and script — is made in
the panel and stored in the database, and an entity's content is a tree of such blocks that this
package prints. It also owns the regions of the layout (header, footer) whose content is blocks.
Pages, articles and products only use the trait `HasBlocks`; the draft, versions and screens are
`webx-ui/module-admin`, the MCP server `webx-ui/mcp`, a page as such `webx-ui/module-pages` — read
their guides when the question is about one of those.

## What it owns

- **Tables** `blocks` (the type: `slug`, `kind`, `group`, `allowed_in`, `is_enabled`, …),
  `block_versions` (one immutable snapshot per save), `block_bundles` (the glued CSS and JS of a
  set of types), `block_regions` (a region's tree, draft and publication).
- **Content shape**: a node is `{ key, type, values }`, plus `hidden: true` when switched off. A
  container holds nodes in one of its values; `@blocks('content')` prints them, up to
  `webx-blocks.max_depth` levels. `$table->blocks()` adds the column to an entity.
- **Shortcodes**: text, textarea and rich text values (repeater items too) reach the template
  with `[name]` resolved (`Rendering\Values`): the editor's text escaped, the shortcode's HTML
  raw; `@shortcodesPlain($field)` for an attribute. A template changes such a field through
  `wx_text($field)->trimEnd('.')`, never a string function inside `{{ }}` (escaped twice; the
  template checks warn, `string-on-text`). The outline an agent reads is plain text.
  With module-audit: checks `blocks.unknown_shortcodes` (a bracket a slip of a registered name)
  and `blocks.hardcoded_values` (a value a panel shortcode holds, typed by hand).
- **Rendering**: `Blocks::render($blocks, $entity)`, `$model->renderBlocks()`, `@webxBlocks` in the
  layout's head (styles and scripts of what the page rendered), `<x-webx-block type="…">` for a
  component, `<x-webx-blocks::region name="header" fallback="components.header" />` for a region.
- **Routes**: bundles `blocks/{hash}.css|js`, `blocks/runtime.js` (`webx-blocks.bundles.path`);
  preview `_preview/{type}/{id}`, `_preview/region/{name}`, `_preview/block-stage` (`webx-blocks.preview.path`).
- **Panel**: sections `blocks` and `regions`; screen `regions.form` (nodes `tabs`, `content`,
  `blocks`, `history`, `versions`); API under `/api/cms/blocks` and `/api/cms/regions`.
- **MCP** tools `blocks_list`, `blocks_get`, `blocks_create`, `blocks_update` (also `rename_to`),
  `blocks_publish`, `blocks_delete`, `blocks_versions`, `blocks_version_restore`, `blocks_usage`,
  `blocks_render`, `blocks_get_content`, `blocks_set_content`, `blocks_edit_content`,
  `blocks_preview_url`, `blocks_regions`, `blocks_region_publish`, `blocks_region_unpublish`,
  `blocks_region_discard`, `blocks_region_versions`, `blocks_region_restore`, `blocks_region_adopt`;
  resources `blocks://guidelines`, `blocks://schema`, `blocks://catalog`, `blocks://fields`,
  `blocks://site`, `blocks://shortcodes`; prompt `design_block`. Scopes `blocks:read`, `blocks:write`.
- **Commands** `webx:blocks:` `export`, `import`, `offered`, `bundles`, `clear`, `regions`, `prune`.
- Also registered: field types `wx-data`, `wx-slot`; registries `BlockOffers`, `BlockShapes`,
  `BlockComponents`; demo content (`resources/demo`). With module-audit: a content source for
  regions, the check `blocks.stray_values` (values for fields the type does not define, repeater
  items included, live and draft) and its fix `blocks.prune-stray` (what `webx:blocks:prune` runs;
  a block of an unknown type is never touched). Deploy, `webx:theme:sync` and `composer update`
  never write a type; `webx:blocks:import --theme` reads the theme layers (`theme_source`/`theme_hash`
  are kept for the debug panel only). The demo never seeds a theme layer's slug.

### Permissions

| Permission       | Opens                                                                                                                                                                                                                                                         |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `blocks.view`    | «Blocks» read-only: types, usage, versions, export; `blocks_list`, `blocks_get`, `blocks_versions`, `blocks_usage`, `blocks_render`; content of a record through `blocks_get_content`, `blocks_preview_url`                                                   |
| `blocks.manage`  | types: create, edit, publish, reorder, import, delete, restore a version, customise a component — writing Blade; the `blocks:write` tools; a record's content through `blocks_set_content`, `blocks_edit_content`                                             |
| `blocks.regions` | the section «Site regions» (`regions` shows by `visibleWith()`), `/api/cms/regions`, the `blocks_region*` tools and `blocks_regions`, a region's content through the content tools; also the catalogue, `fragments` and `render` the editor picks blocks from |

`WEBX_BLOCKS_EDITING=false` makes every type write a 403 whatever the permission (types then come
by `webx:blocks:import`); `blocks/content/{type}/{id}` (panel on the site) asks the record's section.
A route or screen node the site adds next to the module asks the same: `cms.can:blocks.view,blocks.manage`
to read, `cms.can:blocks.manage` to write, `cms.can:blocks.regions` for regions.

## Change it without forking

| You want                                    | Do this                                                                                                                  |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| A block type of the site's own              | make it in the panel's «Blocks», or `blocks_create` → `blocks_render` → `blocks_publish` over MCP                        |
| The site's types in git                     | `php artisan webx:blocks:export` writes `resources/blocks/{slug}.json`; `webx:blocks:import --publish` reads them back   |
| Blocks from another site or a catalogue     | «Blocks» → Export (a pack with the components they call), Import there; the command reads the same pack                  |
| The block types of the site's theme         | `theme/blocks/<slug>.json`, then `php artisan webx:blocks:import --theme`, once, by a person                             |
| Overwrite a type the site already has       | `webx:blocks:import --replace=<slug>` (repeat per type, no `*`); without it a different type is `differs, kept`          |
| The types a module offers (FAQ accordion…)  | `php artisan webx:blocks:offered --install`; a slug the site has, or the theme carries, is not touched                   |
| A region in the layout                      | declare it under `regions` in `config/webx-blocks.php` and print `<x-webx-blocks::region>` with a `fallback`             |
| A type only for one region                  | `"region:header"` in the type's `allowed_in`; `allow` and `max` on the region limit what it takes                        |
| One section on many pages (request form)    | a region with `'fragment' => true`; place the block «Shared block» (`wx-fragment`) or print the tag                      |
| That section with its own text on one page  | «Make it this page's own» in the block's form; agent: `add` its live blocks after the node, `remove` it                  |
| Another group in the picker                 | add it to `groups` in `config/webx-blocks.php` and translate it in the panel's dictionary                                |
| Blocks drawn inside the site's layout       | `WEBX_BLOCKS_LAYOUT=layout` (`<x-layout>` with a `head` slot and the default one)                                        |
| A library a block script can ask for        | `webx.provide('swiper', Swiper)` in the site's bundle, list it in `provides`; the block does `await webx.use('swiper')`  |
| A module's partial replaced by a block      | «Customise» in the panel, or `blocks_create` on the declared slug; `blocks_delete` brings the partial back               |
| A heading with an accent or bold in it      | `wx-rich-text` with `"props": {"inline": true}`, printed `{!! $heading !!}`; not markup typed into a `wx-input`          |
| A phone or e-mail that changes in one place | write `[phone]` in the text (`blocks://shortcodes` lists them); never the number. A site's own: `Shortcodes::register()` |
| A type under another slug                   | «Identifier» in the type's settings, or `blocks_update` with `rename_to`: pages, regions and `allow` lists follow        |
| A bad edit of a page undone                 | `pages_versions`, then `pages_version_restore` (services: `services_*`; a type: `blocks_versions`)                       |
| Bundles written before the first visitor    | list the models in `entities`, run `webx:blocks:bundles --warm`                                                          |
| Values of fields no type defines any more   | `php artisan webx:blocks:prune --dry-run`, then without the flag: live and draft, every listed model and the regions     |
| Other words in the panel                    | `php artisan vendor:publish --tag=webx-blocks-lang`                                                                      |
| All config keys                             | `php artisan vendor:publish --tag=webx-blocks-config`                                                                    |

### Editing content through MCP

Read `blocks_get_content` (entity and id; for a region, entity `region` and its name), then send
`blocks_edit_content` with the `revision` it returned and `ops`: `set`, `unset`, `add`, `move`,
`duplicate`, `remove`, `hide`, `show`, each by `key`. `set` with null keeps the key; `unset` with `fields` takes it out. A
value for a field the type does not define is refused in `set`, `add` and `blocks_set_content` (repeater
items included); one the block already holds may be written back as it was or emptied. Nodes not named stay as they are. The `revision` is required: a write without one is refused, and so is a stale one — `force: true` is for a script that means to overwrite. `blocks_get_content` names in `being_edited_by` who has the entity open in the panel; tell your user before writing under them. Their editor merges your write with theirs block by block and field by field.
For a record the panel edits — a page, a service — the revision is the record's own: the one
`pages_get` or `services_get` returns is the same, and either read's revision may be sent here.
`blocks_set_content` replaces the whole draft tree: anything left out is gone. Both write the
draft; the site changes when a person publishes the entity (a region: `blocks_region_publish`).
`blocks_region_publish`, `blocks_region_unpublish`, `blocks_region_discard` and `blocks_region_restore` take
the `revision` `blocks_get_content` gave for the region: a stale one is refused, and while somebody has the
region open in the panel (`being_edited_by`) one is required — `force: true` goes ahead regardless.
Every value is checked by its field type's rules (bounds, options, dates, colours, links — only
`http(s)`, `mailto`, `tel`, relative paths, `#anchors` — library files, `wx-inbox-form` slugs),
and every block against where it stands: a container's `props.allow`/`props.max` (or the type's
`allow`), a type's `allowed_in` and `max_per_entity`. A refusal names the block's key and field
(`hero [k1], field [title]: …`), shown under the field in the panel; `add` into a type without a
`wx-blocks` field is refused as "not a container".

## Do not

- Do not edit `vendor/webx-ui/module-blocks` or write a type as a Blade file in the site: types live
  in the tables for the panel, versions and preview. Make it in the panel or over MCP; git: `webx:blocks:export`.
- Do not give `blocks.manage` to content editors: a template is Blade, Blade is PHP, saving one is
  running code. Regions need only `blocks.regions`.
- Do not write rows of `blocks` or `block_versions` with SQL: a version is checked and compiled on
  save, publishing renders it first, the cache is forgotten only through the model (or a reset of
  the `blocks` / `regions` groups, «System → Cache»). Use `webx:blocks:import` or the tools.
- Do not rewrite the content of an entity with `saveQuietly()` or a query without `bump('block-usage')`:
  where each type stands is cached on that group and moves only with the models' events.
- Do not publish a template that declares no `data-wx-block="{slug}"` on its root, marks it with
  anything but the exact slug, or uses variables the schema lacks: the script never runs, the
  publish check refuses the last two. `blocks_render` shows both before you publish.
- Do not style with bare element selectors or `@media`: they reach the whole site. Prefix every
  selector with `.b-{slug}` and size with `@container`. On a site with a theme, no literal colour
  or font either — `var(--site-…)` of the theme's vocabulary, no fallback: saving warns.
- Do not delete a type that stands on pages or that other types call: the API answers `409` or
  `422`, `blocks_delete` refuses. `blocks_usage` lists where it stands; remove it there first, or disable it.
- Do not switch a field's `localized` on a type pages already use without reading the warning:
  publishing converts the stored values (a plain value becomes the main language's; a map keeps it)
  and refuses to drop other languages' text unless told to (`drop_translations`).
- Do not remove a region from the config and leave its row: run `webx:blocks:regions --prune`,
  which deletes the saved regions no longer declared, with their versions.

## Check your work

- `blocks_render` on the sample values, then `blocks_preview_url` for the page as it will be; every
  tool that changes something takes `dry_run: true` first.
- `php artisan webx:blocks:import --dry-run` says what an import would change.
- A page slow or heavy on queries: `debug_page` (`webx-ui/module-debug`) times every region and block, with its statements.
- `php artisan webx:blocks:regions` lists the declared regions and whether they are published.
- A page shows a block but no styles: the layout lacks `@webxBlocks` in its head. Stale output
  after a deploy: `php artisan webx:blocks:clear`.

## Read more

- [README.md](README.md) in this directory — the PHP API, templates, bundles, preview, export.
- Guide: https://webx-ui.github.io/webx-ui/guide/blocks
- Regions guide: https://webx-ui.github.io/webx-ui/guide/layout-regions
- Specification (components: §24): https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_BLOCKS.md
- Regions spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_LAYOUT_REGIONS.md
