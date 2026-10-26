# webx-ui/module-configurator

A product configurator: a visitor goes through steps — a construction, the sizes, the options —
sees a picture and a price with its breakdown, and sends a request with the whole configuration.
Built in the panel without code: shower enclosures, doors, partitions, furniture, fences, print.
The section «Configurators» of the panel (id `configurators`) and the MCP tools `configurators_*`
edit it. Drafts and versions are `webx-ui/module-admin`'s, pictures `webx-ui/module-media`'s, the
request form `webx-ui/module-inbox`'s — read their guides for those.

This half stores, checks and prices a configurator, answers the panel's editor
(`@webx-ui/module-configurator`) and builds one over MCP. The block, the view and the script of
the site, and the configuration in a request come in the next stage of the module.

## What it owns

- **Table** `configurators` (`WebxUi\Configurator\Models\Configurator`): `key` (slug, fixed after
  the first publication — blocks name the configurator by it), `title` (translatable), `schema`
  (the published document), `form` (inbox form slug), `draft`, `published_at`, soft deletes.
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
- **MCP** tools `configurators_list`, `_get`, `_create`, `_update`, `_publish`, `_unpublish`,
  `_discard`, `_versions`, `_version_restore`, `_delete`, `_quote`, `_validate`. Resources
  `configurators://catalog`, `configurators://schema`. Scopes `configurators:read`,
  `configurators:write`.
- Also registered: demo content (`resources/demo/shower.json` and the drawings in
  `resources/demo/images`) with its inbox form `configurator-request`; the table moves with
  `webx:snapshot`.

### Permissions

| Permission             | Opens                                                                                                |
| ---------------------- | ---------------------------------------------------------------------------------------------------- |
| `configurators.view`   | the list and the editor, read-only; `configurators_list`, `_get`, `_quote`, `_validate`, `_versions` |
| `configurators.manage` | create, edit, publish, roll back, delete; the `configurators:write` tools                            |

A route or a screen node the site adds next to the module asks the same:
`cms.can:configurators.view,configurators.manage` to read, `cms.can:configurators.manage` to write.

## Change it without forking

| You want                                  | Do this                                                                                 |
| ----------------------------------------- | --------------------------------------------------------------------------------------- |
| A configurator for your product           | build it in the panel, or over MCP: `configurators://schema` → `_validate` → `_create`  |
| Show what a combination costs             | `configurators_quote` with `values` (and `actions` to replay a visitor)                 |
| Another currency for new configurators    | `WEBX_CONFIGURATOR_CURRENCY=EUR` (publish the config: `--tag=webx-configurator-config`) |
| A time-limited sale                       | `discount: { percent, until, label }` in the schema, then publish                       |
| Requests with the configuration           | a form in the inbox, its slug in `form` of the configurator                             |
| Other words of the panel or the breakdown | `php artisan vendor:publish --tag=webx-configurator-lang`                               |
| The demo                                  | `php artisan webx:demo --module=configurators`                                          |

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
  between stands; `media_upload_from_url` gives a key.

## Check your work

- `configurators_validate` with the document: `valid: true`, no problems.
- `configurators_quote` on a few combinations: the `normalized` values, `hidden`, `area`, `lines`
  and `total` are what you meant; a size outside its limits is `values.range`.
- After `configurators_publish`, `configurators_get` shows `status: published` and
  `configurators_versions` a new number.
- Tests: `vendor/bin/phpunit packages/module-configurator/tests` from `php/`.

## Read more

- [README.md](README.md) in this directory — the schema, the price, the MCP scenario, the demo.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_CONFIGURATOR.md
- Drafts and versions: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_PAGES.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
