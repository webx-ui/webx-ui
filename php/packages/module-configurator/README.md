# webx-ui/module-configurator

A product configurator as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: steps of options and sizes built without code, rules for what is shown when, an area
formula, and a price with its breakdown counted on the server.

A visitor chooses a construction, enters the sizes, picks the options, sees a picture and the
price, and sends a request with the whole configuration. The module knows nothing about any
product: shower enclosures, doors, partitions, furniture, windows, fences and print are all one
schema document.

This release is the server half: storing, checking and pricing a configurator, drafts and
versions, the MCP tools and the demo. The panel editor, the block on the site and the request with
its configuration follow.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-media`, `webx-ui/localization`, `webx-ui/mcp`
- `webx-ui/module-inbox` for the request form — suggested, not required

## Install

```bash
composer require webx-ui/module-configurator
php artisan migrate
php artisan webx:demo --module=configurators   # optional: the shower enclosure demo
```

Permissions: `configurators.view`, `configurators.manage`.

## The schema

A configurator is one JSON document. The short version:

```json
{
  "version": 1,
  "currency": "UAH",
  "pricing": { "round": 100 },
  "initial": { "construction": "corner" },
  "discount": { "percent": 10, "until": "2026-11-30", "label": "Autumn" },
  "steps": [
    {
      "key": "construction",
      "type": "cards",
      "label": "Construction",
      "options": [
        {
          "value": "corner",
          "label": "Corner enclosure",
          "image": "media/ab/cd/corner.webp",
          "area": "(w+d)*h/1e6",
          "price": { "fixed": 8000, "perM2": 4420 },
          "defaults": { "h": 2000, "w": 900, "d": 900 }
        }
      ]
    },
    {
      "key": "size",
      "type": "dimensions",
      "title": "Sizes",
      "fields": [
        { "key": "h", "label": "Height", "unit": "mm", "min": 1300, "max": 2400, "step": 50 },
        { "key": "w", "label": "Width", "unit": "mm", "min": 600, "max": 1600, "step": 50 },
        {
          "key": "d",
          "label": "Depth",
          "unit": "mm",
          "min": 600,
          "max": 1600,
          "when": { "construction": ["corner"] }
        }
      ]
    },
    {
      "key": "hardware",
      "type": "swatches",
      "label": "Hardware",
      "options": [
        { "value": "chrome", "label": "Chrome", "swatch": "#c9ced3" },
        {
          "value": "gold",
          "label": "Gold",
          "swatch": "linear-gradient(135deg, #b8862b, #f3d27a)",
          "price": { "percent": 12 }
        }
      ]
    }
  ]
}
```

- Step types: `cards` (a picture each), `swatches` (`swatch` — a colour or gradient — or
  `swatchImage`), `buttons`, `dimensions` (number fields).
- `when` / `unless` on a step, an option or a field: `{ step: [values] }`, only steps above.
- Texts are a string (the default language) or `{ "en": "…", "uk": "…" }`.
- Pictures are media library keys, not ids.

The full reference — every key, the rules and the problem codes — is the MCP resource
`configurators://schema`, and the specification `docs/architecture/WEBX_UI_MODULE_CONFIGURATOR.md`.

## The price

1. For each visible choice step, in order: `fixed` as it is, `perM2` × the area (the formula of
   the chosen option of the one step that has formulas). Every line is rounded to a cent.
2. `percent` of each visible step — of the sum of step 1, never of another percent.
3. The discount, when it is in force on the day: a percent of what 1 and 2 give.
4. The total, rounded to `pricing.round`, half away from zero, never below 0.

The engine is pure PHP (`WebxUi\Configurator\Engine\Engine`); `Pricing\Quoter` is what the rest
calls. The npm package has a JS copy of the same rules for the instant price on the page; the
cases in `resources/fixtures/pricing/` hold both to the same numbers.

## MCP

`configurators_list`, `_get`, `_create`, `_update`, `_publish`, `_unpublish`, `_discard`,
`_versions`, `_version_restore`, `_delete` (every write takes `dry_run`), `configurators_quote` and
`configurators_validate` (read-only). An agent builds a configurator end to end:

1. read `configurators://schema` (and `configurators://catalog` for what exists);
2. `media_upload_from_url` for the pictures;
3. `configurators_validate` the document;
4. `configurators_create` with `dry_run: true`, then without;
5. `configurators_quote` on a few combinations (`actions` replay a visitor);
6. `configurators_publish`.

## The demo

`php artisan webx:demo --module=configurators` makes a shower enclosure configurator with no
company in it and example prices: eight constructions, nine glasses, frosting, six kinds of
hardware, two picture rules. Its words are in English, Russian and Ukrainian; its pictures are
neutral drawings put into the media library. With the inbox it gets the request form
`configurator-request`.

## License

MIT
