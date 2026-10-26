# webx-ui/module-configurator

A product configurator: a visitor goes through steps — a construction, the sizes, the options —
sees a picture and a price with its breakdown, and sends a request with the whole configuration.
Built in the panel without code: shower enclosures, doors, partitions, furniture, fences, print.
The section «Configurators» of the panel (id `configurators`) and the MCP tools `configurators_*`
edit it. Drafts and versions are `webx-ui/module-admin`'s, pictures `webx-ui/module-media`'s, the
request form `webx-ui/module-inbox`'s — read their guides for those.

It stores, checks and prices a configurator for the panel's editor and MCP, prints it on the site,
links it with the catalogue and puts the configuration, priced again, into a request of the inbox.

## What it owns

- **Table** `configurators` (`WebxUi\Configurator\Models\Configurator`): `key` (slug, fixed after
  the first publication — blocks name the configurator by it), `title` (translatable), `schema`
  (the published document), `form` (inbox form slug), `page` (`wx-link`: where its block stands —
  not drafted, not versioned), `draft`, `published_at`, soft deletes.
  `HasDraft` + `HasVersions`: the site prices by the columns, the panel edits the draft.
- **The schema document** — one JSON: `version`, `currency`, `pricing.round`, `initial`,
  `discount`, `steps` (`cards`, `swatches`, `buttons`, `dimensions`), `previews`. The reference is
  the MCP resource `configurators://schema`, built from `Schema\Validator`'s constants.
- **The engine** `Engine\Engine` — pure functions: `resolve` (visibility, the choice, the sizes),
  `select`/`set` (a visitor's actions), `price` (fixed and perM2 lines, simple percents, the
  discount, the rounding). `Engine\Formula` — the area formula without `eval`. The JS copy in the
  npm package implements the same text.
- **Parity cases** `resources/fixtures/pricing/*.json` — one file per case, read by phpunit
  (`PricingParityTest`) and by the npm package's vitest. Change a rule — change a case.
- **The check** `Schema\Validator`: problems `{ path, code, message }`; a draft is saved with
  them, a publication is refused (`Panel\ConfiguratorWriter`).
- **Pricing for everyone** `Pricing\Quoter`: values → price, with the library behind
  `image.unknown`; the words of a breakdown line — `Pricing\Breakdown`.
- **Panel API** under `api/cms/configurators` (`routes/api.php`): the list, the record with its
  `revision` (a stale one is a 409), the draft, `publish` (422 with `problems`), `unpublish`,
  `discard`, versions, the bin, `validate` and `{id}/quote`. The editor's heartbeat knows the
  record as `configurators`.
- **Field type** `wx-configurator` (`Screens\ConfiguratorFieldType`): a configurator by its key; a
  key nobody has is refused when written anew. Its picker is the relation target `configurator`.
- **On the site** `<x-webx-configurator key="…" title="…" :preset="[…]" />` (`View\Components\Configurator`,
  view `webx-configurator::configurator`): the server prints every step, the price of the start,
  «from N», the picture, the discount and the messengers of `contacts`; the widget
  (`dist/configurator.js`, served at `webx/configurator/configurator.js`, built from
  `resources/js` and the npm engine — `npx vite build` here) only switches. Base look
  `resources/theme/configurator.css`. Published configurators are cached in the group
  `configurators`.
- **Block** `configurator` (`resources/blocks`, `webx:blocks:offered --install`): fields
  `configurator`, `title` (`-` — none), `preset` (`wx-configurator-preset`: values and sizes of the
  configurator beside it, no JSON).
- **Request** — the inbox's enclosure `configurator` (`Inbox\ConfiguratorEnclosure`): the radios and
  sizes belong to the dialog's form by `form=`, so they go with and without JavaScript; the server
  prices them and writes one answer row with no field (text + `payload`: selection, sizes, area,
  discount, lines, total). Only for the form the configurator names; a size out of limits is 422
  `configurator.values.<field>`.
- **Catalogue link** (`Catalog\\*`, only when `webx-ui/module-catalog` is installed; the catalogue
  knows nothing of it). Product → configurator: table `configurator_product` (`Models\ProductLink`,
  product id, key, `preset`), the part `configurator` of the product form (`configurator.key`,
  `configurator.preset` — also for `catalog_products_update`), the button in `catalog.product.aside`
  (view `webx-configurator::open`) to `page` with `?configurator=<key>&preset[<step>]=<value>`
  — the component reads it as `initial`. Configurator → product: `"product": "<SKU>"` on an option
  (`product.invalid`, `product.unknown`; picker — relation target `configurator-product`); while
  chosen, the site shows up to 4 gallery pictures and a link — nothing if the product is off the site.
- **MCP** tools `configurators_list`, `_get`, `_create`, `_update`, `_publish`, `_unpublish`,
  `_discard`, `_versions`, `_version_restore`, `_delete`, `_quote`, `_validate`. Resources
  `configurators://catalog`, `configurators://schema`. `_create`/`_update` take `page`; `_get` answers
  `page`, `option_products` and `opened_from_products`. Scopes `configurators:read`, `:write`.
- Also registered: demo (`resources/demo`, its inbox form `configurator-request`, the first
  constructions linked to the catalogue's demo products when they are there); `webx:snapshot`.

### Permissions

| Permission             | Opens                                                                                                |
| ---------------------- | ---------------------------------------------------------------------------------------------------- |
| `configurators.view`   | the list and the editor, read-only; `configurators_list`, `_get`, `_quote`, `_validate`, `_versions` |
| `configurators.manage` | create, edit, publish, roll back, delete; the `configurators:write` tools                            |

A route or a screen node the site adds next to the module asks the same:
`cms.can:configurators.view,configurators.manage` to read, `cms.can:configurators.manage` to write.

## Change it without forking

| You want                                  | Do this                                                                                   |
| ----------------------------------------- | ----------------------------------------------------------------------------------------- |
| A configurator for your product           | build it in the panel, or over MCP: `configurators://schema` → `_validate` → `_create`    |
| Show what a combination costs             | `configurators_quote` with `values` (and `actions` to replay a visitor)                   |
| Another currency for new configurators    | `WEBX_CONFIGURATOR_CURRENCY=EUR` (publish the config: `--tag=webx-configurator-config`)   |
| A product opening the configurator        | `page` of the configurator, then `configurator.key` / `.preset` of the product            |
| A time-limited sale                       | `discount: { percent, until, label }` in the schema, then publish                         |
| Requests with the configuration           | a form in the inbox, its slug in `form` of the configurator                               |
| Other words of the panel or the breakdown | `php artisan vendor:publish --tag=webx-configurator-lang`                                 |
| The demo                                  | `php artisan webx:demo --module=configurators`                                            |
| Another layout of the configurator        | `vendor:publish --tag=webx-configurator-views`; keep the `data-webx-configurator-*` marks |

### Classes

The theme's contract (THEMES §10), in `resources/theme/configurator.css`; restyle with the same class:
`webx-configurator`, `webx-configurator__title`, `webx-configurator__layout`, `webx-configurator__preview`,
`webx-configurator__preview-image`, `webx-configurator__steps`, `webx-configurator__step`
(+ `webx-configurator__step--<type>`), `webx-configurator__step-title`, `webx-configurator__hint`,
`webx-configurator__options` (+ `webx-configurator__options--<type>`), `webx-configurator__option`
(+ `webx-configurator__option--card`, `webx-configurator__option--swatch`, `webx-configurator__option--button`,
`is-selected`), `webx-configurator__radio`, `webx-configurator__option-image`, `webx-configurator__swatch`,
`webx-configurator__option-text`, `webx-configurator__option-label`, `webx-configurator__option-subtitle`,
`webx-configurator__option-from`, `webx-configurator__fields`, `webx-configurator__field`,
`webx-configurator__field-label`, `webx-configurator__stepper`, `webx-configurator__stepper-button`,
`webx-configurator__input`, `webx-configurator__range`, `webx-configurator__summary`,
`webx-configurator__price`, `webx-configurator__total`, `webx-configurator__old-price`,
`webx-configurator__discount`, `webx-configurator__breakdown`, `webx-configurator__breakdown-title`,
`webx-configurator__lines`, `webx-configurator__line` (`is-discount`), `webx-configurator__line-text`,
`webx-configurator__line-amount`, `webx-configurator__note`, `webx-configurator__actions`,
`webx-configurator__submit`, `webx-configurator__messenger` (+ `webx-configurator__messenger--<kind>`),
`webx-configurator__messenger-icon`, `webx-configurator__sr`, `webx-configurator__recap`,
`webx-configurator__recap-title`, `webx-configurator__recap-list`, `webx-configurator__recap-total`,
`webx-configurator__live-list`, `webx-configurator__live`, `webx-configurator__live-title`, `webx-configurator__live-images`,
`webx-configurator__live-item`, `webx-configurator__live-link`, `webx-configurator__live-image`, `webx-configurator__live-product`;
on a product's page `webx-configurator-open`.
Local tokens on `.webx-configurator`: `--webx-configurator-gap`, `-aside`, `-card`, `-swatch-size`, `-control`, `-selected`, `-corner` (set by the widget).

## Do not

- Do not edit anything in `vendor/webx-ui/module-configurator` or copy it into the site. The rows
  above are the supported ways; if none fits, the package is missing a seam — say so.
- Do not price on the client and trust it: the server prices by the values it is sent, through
  `Quoter`; a price in a request is never read.
- Do not change the engine without a parity case: PHP and JS read the same files, a rule changed in
  one copy only shows up as a different price on the site.
- Do not write the `schema` column or `draft` with SQL: the check, the versions and the editor's
  revision are skipped. Use `ConfiguratorWriter` or the MCP tools.
- Do not rename `key` of a published configurator: blocks of the site name it by it.
- Do not reference pictures by media id: the document keeps library keys (`media/…`), which move
  between stands. `media_upload_from_url` answers with the id; the key is `path` of `media_get_file`.
- Do not put a value of a conditional step into `quote`'s `values` without the step it depends on:
  `values` are resolved before `actions`, and a step hidden at that point drops its value.

## Check your work

- `configurators_validate` with the document: `valid: true`, no problems.
- `configurators_quote` on a few combinations: the `normalized` values, `hidden`, `area`, `lines`
  and `total` are what you meant; a size outside its limits is `values.range`.
- After `configurators_publish`, `configurators_get` shows `status: published` and
  `configurators_versions` a new number.
- On a page: `blocks_edit_content` adds the block `configurator` with the values `configurator`
  (the key), `title` and `preset`; the page shows the same total as `configurators_quote`.
- Tests: `vendor/bin/phpunit packages/module-configurator/tests` from `php/`.

## Read more

- [README.md](README.md) in this directory — the schema, the price, the MCP scenario, the demo.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_CONFIGURATOR.md
- Drafts and versions: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_PAGES.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
