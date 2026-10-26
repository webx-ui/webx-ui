# Configurators

`@webx-ui/module-configurator` is the configurators as a section of the panel, and
`webx-ui/module-configurator` on the server is what it edits and what prices them. This page is
both, because neither is useful alone.

A configurator is a **product built to size**: a visitor goes through steps — a construction, the
sizes, the glass, the fittings — sees a picture and a price with its breakdown, and sends a request
with the whole configuration. The module knows nothing about any product: shower enclosures, doors,
partitions, furniture, fences and print are all one schema document, built in the panel without
code.

## Install

```bash
pnpm add @webx-ui/module-configurator
composer require webx-ui/module-configurator
php artisan migrate
php artisan webx:demo --module=configurators   # the shower enclosures, if you want them
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { configurators } from '@webx-ui/module-configurator'
import '@webx-ui/module-configurator/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...configurators()],
}).mount()
```

`configurators()` is an array — spread it. `configurators({ path: '/builder' })` mounts the section
elsewhere in the panel. Two permissions: `configurators.view` opens the list and the editor
read-only, `configurators.manage` writes and publishes.

## The schema

Everything a configurator is lives in one JSON document, `schema`:

```json
{
  "version": 1,
  "currency": "UAH",
  "pricing": { "round": 100 },
  "initial": { "construction": "corner_slide" },
  "discount": { "percent": 10, "until": "2026-11-30", "label": "Autumn sale" },
  "steps": [
    {
      "key": "construction",
      "type": "cards",
      "label": "Construction",
      "options": [
        {
          "value": "corner_slide",
          "label": "Corner sliding enclosure",
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
          "when": { "construction": ["corner_slide"] }
        }
      ]
    }
  ]
}
```

- **Steps** are `cards` (options with a picture and a «from» price), `swatches` (a colour, a
  gradient or a small picture), `buttons`, and `dimensions` (number fields with − and +).
- **`when` / `unless`** — `{ step: [values] }` on a step, an option or a field: shown only when
  (or except when) a step above is chosen with one of the values. A hidden step is not priced.
- **The price** — `fixed` adds a sum, `perM2` multiplies a rate by the area, `percent` adds a
  percent of the sum before percents. The area is the formula of the chosen option of the **main
  step** — the one step whose options have `area`. Then the discount, then the rounding.
- **Texts** are a string or `{ "uk": "…", "en": "…" }`; pictures are media library keys.

The full reference is the specification
([`WEBX_UI_MODULE_CONFIGURATOR.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_CONFIGURATOR.md))
and, for an agent, the MCP resource `configurators://schema`.

## The editor

The list says which configurators there are, whether each is on the site, the form its requests
go to and how many steps it has. A new one is a name and a key — the key is what a block of the
site names it by, and it is fixed once the configurator has been published.

The editor has four tabs:

- **Builder** — the steps on the left, dragged into order and unfolded one at a time; the
  configurator as a visitor sees it on the right. Conditions are **chosen**, not typed: a step
  above, then its options. A step or a value renamed takes every condition, formula, `defaults`
  and `limits` that names it along; one removed takes them away. The preview is priced by the same
  engine as the site; «Make this the starting choice» writes what is clicked into `initial`.
- **JSON** — the whole document. «Apply» asks the server's check first; problems are listed with
  their places, and a click opens the place in the builder.
- **Settings** — the name, the key, the Inbox form, the currency, the rounding, the discount.
- **History** — every publication, put back into the draft with one click, and the copies of the
  draft between publications.

The draft saves itself, **problems and all**: a configurator half built is a draft. Only a
publication waits until the schema has no problems — they are listed over the steps and said again
at the field they are about.

## Two engines, one set of cases

The server's engine is the truth: the site's requests are priced by it, and a price sent by a
browser is never read. The panel — and, next, the widget of the site — draws the number first with
the JS copy:

```ts
import { price, resolve, select, validate } from '@webx-ui/module-configurator'

validate(schema, 'en') // [] when it can be published
let state = resolve(schema, {}) // the starting choice
state = select(schema, state, 'glass', 'graphite')
price(schema, state, '2026-10-26').total
```

Both copies are held to the same JSON cases,
`php/packages/module-configurator/resources/fixtures/pricing/*.json`: phpunit and vitest read the
one directory. Changing a rule means changing a case.

## A configurator in a field

`wx-configurator` is a field type for any screen or block: one configurator, chosen by its name and
kept by its key. A key nobody has is refused when it is written anew; one whose configurator was put
in the bin later stays and is marked.

```json
{ "id": "configurator", "type": "wx-configurator", "name": "configurator", "label": "Configurator" }
```

## On the site

A configurator reaches a page as the offered block `configurator`:

```bash
php artisan webx:blocks:offered --install --module=configurators
```

The block has three fields: the **configurator** (`wx-configurator`), the **heading** (empty is the
configurator's name, `-` is none) and **where it starts** (`wx-configurator-preset`) — a value for
any step and a number for any size of the configurator chosen beside it, picked from its published
steps, never typed as JSON. What the schema no longer has is dropped when the block is printed.

Without a block, the same thing in a template of the site:

```blade
<x-webx-configurator key="shower" />
<x-webx-configurator key="shower" title="-" :preset="['construction' => 'niche', 'h' => 2100]" />
```

**The server prints everything; the script only switches.** Every step, option and size is in the
HTML — what the start hides with `hidden` — with the price of the start and its breakdown («How the
price is made»), «from N» on the cards (the option at its defaults, every other step as it starts),
the picture by the `previews` rules, the discount and the messengers of the site's contacts beside
the button. The widget (`webx/configurator/configurator.js`, the npm engine built in, 5 KB gzip)
recounts the price as the visitor chooses, hides what the choice hides, swaps the picture and keeps
the struck-out price only while the discount is in force.

**A discount** is `discount: { percent, until, label }` in the schema: the label (`−20% до 31.10`)
and the old price struck out next to the total, until its last day; after it neither is shown and
the server does not give it.

**The request.** The button opens the configurator's inbox form in a dialog (a sheet on a phone).
The radios and the sizes belong to that form by `form=`, so they are sent with the visitor's
contacts with JavaScript and without it. The server prices them again from the published schema —
a price in the request is never read — and writes the configuration into the submission as one
answer with no field: the text the letter, Telegram and the export show, and a structure the
inbox's card draws (selection, sizes, area, lines, discount, total). A size outside its limits is a
422 under `configurator.values.<field>`; a configuration sent to a form its configurator does not
name is refused.

**The look** is `resources/theme/configurator.css` of the module, on site tokens: steps on the left
and the price on the right when the configurator is at least 45rem wide; one column under it, the
picture above the steps and the price with the button stuck to the bottom of the screen while the
configurator is on it. A theme restyles it with the same classes (`### Classes` in the package's
`AGENTS.md`), or publishes the view: `--tag=webx-configurator-views`.

## With the catalogue

When `webx-ui/module-catalog` is installed, the configurator links itself with it both ways — from
its own side: the catalogue knows nothing of configurators, and without it nothing below exists.

**From a product to the configurator.** The product's form gets a card «Configurator» in its main
tab: the configurator (`configurator.key`) and where it starts from this product
(`configurator.preset`, chosen from its steps and sizes). The configurator says where its block
stands — **Configurator page** in its settings, a link to a page or an address. Once it is
published and its page is on the site, the product's page shows «Calculate in the configurator»
(the point `catalog.product.aside`), leading to

```text
/shower-configurator?configurator=shower&preset[construction]=walk_in#webx-configurator-shower
```

The address only asks: the configurator reads `preset` as it reads its `initial` — an unknown
step or value is dropped, a size is pulled into its limits — and a request is priced by the server
from what is sent, as always. The link is kept in the configurator's own table
(`configurator_product`), never in a column of the product.

**From an option to a product.** An option may name a product by its article number —
`"product": "GLASS-GRAPHITE"`, picked in the option's editor by name or article. While the option
is chosen, the configurator shows «Graphite in real life» under itself: up to four pictures of the
product's gallery and a link to it. A product that is unpublished, binned or in no visible category
shows nothing. An article number the catalogue does not have is refused when it is new
(`product.unknown`); one that went later does not lock the editor.

**For an agent.** `configurators_update` writes `page` and the options' `product` with the
schema; `configurators_get` answers the page, `option_products` (each found or not, on the site or
not) and `opened_from_products`. The product side is `catalog_products_update` with
`configurator.key` and `configurator.preset` — `catalog://product-parts` describes them.

The demo links the first constructions of the shower configurator to the catalogue's demo
products when those are on the site.

## The panel's API

Under the panel's API path, `configurators`: the list (`?trashed=1`), `POST` a new one,
`GET`/`PUT {id}` with a `revision` (a stale one is a 409 with the record as it is),
`{id}/publish` (422 with `problems`), `unpublish`, `discard`, `versions`,
`versions/{n}/restore`, `restore` out of the bin, `validate` (`{ schema }`) and `{id}/quote`
(`{ values, actions?, draft? }`).
