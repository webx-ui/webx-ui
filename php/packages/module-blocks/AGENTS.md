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
- **Rendering**: `Blocks::render($blocks, $entity)`, `$model->renderBlocks()`, `@webxBlocks` in the
  layout's head (styles and scripts of what the page rendered), `<x-webx-block type="…">` for a
  component, `<x-webx-blocks::region name="header" fallback="components.header" />` for a region.
- **Routes**: bundles under `config('webx-blocks.bundles.path')` (`blocks/{hash}.css|js`,
  `blocks/runtime.js`); the preview under `config('webx-blocks.preview.path')` (`_preview/{type}/{id}`,
  `_preview/region/{name}`, `_preview/block-stage`).
- **Panel**: sections `blocks` and `regions`; screen `regions.form` (nodes `tabs`, `content`,
  `blocks`, `history`, `versions`); API under `/api/cms/blocks` and `/api/cms/regions`;
  permissions `blocks.view`, `blocks.manage` (writes Blade), `blocks.regions` (edits regions).
- **MCP** tools `blocks_list`, `blocks_get`, `blocks_create`, `blocks_update`, `blocks_publish`,
  `blocks_render`, `blocks_get_content`, `blocks_set_content`, `blocks_edit_content`,
  `blocks_preview_url`, `blocks_regions`, `blocks_region_publish`, `blocks_region_unpublish`;
  resources `blocks://guidelines`, `blocks://schema`, `blocks://catalog`, `blocks://fields`,
  `blocks://site`; prompt `design_block`. Scopes `blocks:read`, `blocks:write`.
- **Commands** `webx:blocks:export`, `webx:blocks:import`, `webx:blocks:offered`,
  `webx:blocks:bundles`, `webx:blocks:clear`, `webx:blocks:regions`, `webx:blocks:prune`.
- Also registered: field types `wx-data` and `wx-slot`, the registries `BlockOffers`,
  `BlockShapes`, `BlockComponents`, an audit content source for regions when
  `webx-ui/module-audit` is installed, demo content (`resources/demo`).

## Change it without forking

| You want                                   | Do this                                                                                                                 |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- |
| A block type of the site's own             | make it in the panel's «Blocks», or `blocks_create` → `blocks_render` → `blocks_publish` over MCP                       |
| The site's types in git                    | `php artisan webx:blocks:export` writes `resources/blocks/{slug}.json`; `webx:blocks:import --publish` reads them back  |
| The types a module offers (FAQ accordion…) | `php artisan webx:blocks:offered --install`; a slug the site already has is never touched                               |
| A region in the layout                     | declare it under `regions` in `config/webx-blocks.php` and print `<x-webx-blocks::region>` with a `fallback`            |
| A type only for one region                 | `"region:header"` in the type's `allowed_in`; `allow` and `max` on the region limit what it takes                       |
| Another group in the picker                | add it to `groups` in `config/webx-blocks.php` and translate it in the panel's dictionary                               |
| Blocks drawn inside the site's layout      | `WEBX_BLOCKS_LAYOUT=layout` (`<x-layout>` with a `head` slot and the default one)                                       |
| A library a block script can ask for       | `webx.provide('swiper', Swiper)` in the site's bundle, list it in `provides`; the block does `await webx.use('swiper')` |
| A module's partial replaced by a block     | «Customise» in the panel, or `blocks_create` on the declared slug; deleting the type brings the partial back            |
| Types read-only on production              | `WEBX_BLOCKS_EDITING=false`; types then arrive by `webx:blocks:import`                                                  |
| Bundles written before the first visitor   | list the models in `entities`, run `webx:blocks:bundles --warm`                                                         |
| Values of fields no type defines any more  | `php artisan webx:blocks:prune --dry-run`, then without the flag: live and draft, every listed model and the regions    |
| Other words in the panel                   | `php artisan vendor:publish --tag=webx-blocks-lang`                                                                     |
| All config keys                            | `php artisan vendor:publish --tag=webx-blocks-config`                                                                   |

### Editing content through MCP

Read `blocks_get_content` (entity and id; for a region, entity `region` and its name), then send
`blocks_edit_content` with the `revision` it returned and `ops`: `set`, `unset`, `add`, `move`,
`remove`, `hide`, `show`, each by `key`. `set` with null keeps the key; `unset` with `fields` takes it out. Nodes not named stay as they are; a stale revision is refused.
`blocks_set_content` replaces the whole draft tree: anything left out is gone. Both write the
draft; the site changes when a person publishes the entity (a region: `blocks_region_publish`).

## Do not

- Do not edit `vendor/webx-ui/module-blocks` or write a block type as a Blade file in the site:
  types live in the tables so the panel, versions and preview see them. Make it in the panel or
  over MCP and keep it in git with `webx:blocks:export`.
- Do not give `blocks.manage` to content editors: a template is Blade, Blade is PHP, saving one is
  running code. Regions need only `blocks.regions`.
- Do not write rows of `blocks` or `block_versions` with SQL: a version is checked and compiled on
  save, publishing renders it first, and the cache is forgotten only through the model. Use
  `webx:blocks:import` or the tools.
- Do not publish a template that declares no `data-wx-block="{slug}"` on its root or uses
  variables the schema lacks: the script never runs, the publish check refuses. `blocks_render`
  shows both before you publish.
- Do not style with bare element selectors or `@media`: they reach the whole site. Prefix every
  selector with `.b-{slug}` and size with `@container`.
- Do not delete a type that stands on pages or that other types call: the API answers `409` or
  `422`. Remove it from the content first, or disable it.
- Do not remove a region from the config and leave its row: run `webx:blocks:regions --prune`,
  which deletes the saved regions no longer declared, with their versions.

## Check your work

- `blocks_render` on the sample values, then `blocks_preview_url` for the page as it will be; every
  tool that changes something takes `dry_run: true` first.
- `php artisan webx:blocks:import --dry-run` says what an import would change.
- `php artisan webx:blocks:regions` lists the declared regions and whether they are published.
- A page shows a block but no styles: the layout lacks `@webxBlocks` in its head. Stale output
  after a deploy: `php artisan webx:blocks:clear`.

## Read more

- [README.md](README.md) in this directory — the PHP API, templates, bundles, preview, export.
- Guide: https://webx-ui.github.io/webx-ui/guide/blocks
- Regions guide: https://webx-ui.github.io/webx-ui/guide/layout-regions
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_BLOCKS.md
- Components and regions: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_BLOCK_COMPONENTS.md,
  https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_LAYOUT_REGIONS.md
