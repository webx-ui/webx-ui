# webx-ui/module-tariffs

Price cards of the site: a name, a badge, a price in a currency of the site's list — or words
instead of one («On request») — a period, what the plan includes, a description, one button and a
«recommended» mark; gathered in flat groups and, when the site has services, linked to them. A
tariff has no page and no address — it reaches the site in a block (a slider, a grid) or through
`tariffs()` in a template; the page brings the address, the SEO and the menu entry. The section
«Tariffs» of the panel and the MCP tools `tariffs_*` edit it. The blocks are
`webx-ui/module-blocks`, the groups, screens and bin `webx-ui/module-admin`, the links to services
`webx-ui/module-services` — read their guides for questions about those.

## What it owns

- **Tables** `tariffs` (`WebxUi\Tariffs\Models\Tariff`), `tariff_categories` — the groups
  (`WebxUi\Tariffs\Models\TariffCategory`) — and the link `tariff_category_tariff`. A published
  tariff is shown in every language: the name, badge, period and price words fall back to the
  default language; the description and the rows of the list are printed only where written.
- **No public route.** The collection source `tariffs` and the offered block type `tariffs`
  (`resources/blocks/tariffs.json`) are how a tariff reaches a page.
- **Helper** `tariffs()` — declared only when the site has no function of that name.
- **Config** `webx-tariffs.currencies` (ISO code → symbol; the first is a new tariff's) and
  `webx-tariffs.variants` (button look key → panel label).
- **Panel screens** `tariffs.form` (nodes `tariff`, `name`, `badge`, `featured`, `pricing`,
  `price`, `currency`, `period`, `price-text`, `includes`, `features`, `about`, `description`,
  `button`, `button-label`, `button-link`, `button-variant`, `settings`, `categories`, `services`,
  `published`, `project-fields`) and `tariffs.category-form` (`naming`, `title`, `is-visible`,
  `project-fields`).
- **API** under `/api/cms/tariffs` and `/api/cms/tariffs/categories`; permissions `tariffs.view`,
  `tariffs.manage`, `tariffs.groups.manage`.
- **MCP** tools `tariffs_list`, `tariffs_get`, `tariffs_create`, `tariffs_update`,
  `tariffs_delete`, `tariffs_reorder`, and `tariff_groups_list`, `tariff_groups_create`,
  `tariff_groups_update`, `tariff_groups_delete`, `tariff_groups_reorder`; resource
  `tariffs://catalog`. Scopes `tariffs:read`, `tariffs:write`, `tariff-groups:read`,
  `tariff-groups:write`.
- Also registered: the panel group `tariffs` (entries Tariffs and Groups), demo content
  (`resources/demo`).

## Change it without forking

| You want                            | Do this                                                                                      |
| ----------------------------------- | -------------------------------------------------------------------------------------------- |
| The Tariffs block on the site       | `php artisan webx:blocks:offered --install --module=tariffs` (a type already there is kept)  |
| Different markup, «750$» not «$750» | edit the site's copy of the block type `tariffs` in the panel — not the JSON in the package  |
| Another currency                    | `currencies` in `config/webx-tariffs.php` (`vendor:publish --tag=webx-tariffs-config`)       |
| Another button look                 | `variants` in the same file; the key becomes the class `b-tariffs__button--<key>`            |
| Tariffs in a template of the site   | `tariffs()->in($groups)->relatedTo('service', $service)->take(3)`; `categories()` by group   |
| A field of the site's own           | a patch: `Screens::extend('tariffs.form', [...])` adding into `project-fields`; read `extra` |
| Other words in the panel            | `php artisan vendor:publish --tag=webx-tariffs-lang`                                         |

A currency key that is not three capital letters is skipped. Taking a currency or a look out of
the list locks nothing: a tariff that has it keeps it; on the site a lost currency prints as its
code, a lost look as the first one. A project field reaches the card under `fields`.

## Do not

- Do not edit anything in `vendor/webx-ui/module-tariffs`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not add currencies or looks with a screen patch on the `currency` or `button-variant`
  select: the server checks against the config and refuses (422). Change the config.
- Do not store «Free» as price `0`: zero is a price and prints as «$0». Leave the price empty and
  write the words in `price_text`.
- Do not add a migration for a project field: the patch and the `extra` column are the place.
- Do not add a route, a page or `Offer` markup for one tariff: a tariff has no address by design.
  Put a tariffs block on a page of `webx-ui/module-pages`.
- Do not pass a group as a slug to `tariffs()->in()`: groups have no slugs, and a string that is
  not a number is a filter nothing passes. Pass ids or models.
- Do not delete rows with SQL: deleting sends a tariff to the bin and `POST tariffs/{id}/restore`
  brings it back to its places; a raw delete skips the bin for good.

## Check your work

- `php artisan webx:doctor` — among other things, whose `tariffs()` the site calls.
- Open the page with the block on the site: a new tariff is unpublished until someone publishes it.
- With MCP: read `tariffs://catalog` first (the currencies and looks the site accepts, every group
  with its tariffs), then `tariffs_get`; every tool that changes something takes `dry_run: true`.

## Read more

- [README.md](README.md) in this directory — the card, `tariffs()`, the block, the panel API.
- Guide: https://webx-ui.github.io/webx-ui/guide/tariffs
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_TARIFFS.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
