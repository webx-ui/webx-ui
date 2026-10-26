# @webx-ui/module-configurator

## 0.1.0

### Minor Changes

- b4296aa: The field `wx-configurator-preset` — where a block's configurator starts: a value for any step and
  a number for any size of the configurator chosen beside it, picked from its published steps, never
  typed as JSON; «as the configurator starts» keeps nothing. And the panel type
  `inbox-enclosure:configurator`, which draws the configuration of a request in the inbox's card: the
  selection, the sizes, the area, the lines and the total as the server counted them.
- b4296aa: A configurator and the catalogue, linked both ways from the configurator's side — the catalogue
  knows nothing of it, and without the catalogue nothing changes.

  - **A product opens a configurator.** The product's form gets a card «Configurator» with the key and
    where it starts (`configurator.key`, `configurator.preset`, also for `catalog_products_update`),
    kept in the configurator's own table `configurator_product`. Its page shows «Calculate in the
    configurator», leading to the configurator's new **page** setting with the preset in the address,
    which the server reads as `initial`.
  - **An option shows a product «in real life».** `"product": "<SKU>"` on an option, picked by name
    or article number in the editor (`product.invalid`, `product.unknown`): while it is chosen, up to
    four pictures of the product's gallery and a link to it, under the configurator — nothing for a
    product the site does not show.
  - **MCP:** `page` in `configurators_create`/`_update`; `configurators_get` answers the page, the
    options' products and the products that open it. The demo links its constructions to the
    catalogue's demo products when they are there.

- b4296aa: New package `@webx-ui/module-configurator` — the section «Configurators» of the panel.

  - **The engine in JS.** `resolve`, `select`, `set`, `price`, `roundTo` and the area formula, and
    `validate` with the server's paths and codes — no Vue, no dependencies. Held to the same parity
    cases as the PHP engine (`resources/fixtures/pricing/` of the composer package).
  - **The list** — name and key, status, request form, steps; a new configurator from a name and a
    key; the bin.
  - **The editor** — Builder · JSON · Settings · History. Steps and options dragged into order;
    conditions «shown when / hidden when» chosen from the steps above and their options; pictures
    and swatches from the media library; prices, the area formula with its keys a click away,
    `defaults` and `limits`; picture rules. A live preview priced by the JS engine, with the total
    struck through under a discount and the breakdown line by line; «Make this the starting choice».
    Problems are listed and said at their fields; the draft saves itself with them, a publication
    waits. JSON mode checks a document on the server before it goes into the draft. Settings: name,
    key, request form, currency, rounding, the discount with its last day and name.
  - **The field `wx-configurator`** — a configurator chosen by name and kept by key.

### Patch Changes

- b4296aa: What an agent building a configurator over MCP stumbled on, fixed after a run end to end.

  - **A card picture that is not a library key is one problem, not two.** `image.missing` was told
    twice for `{ "id": 35 }` — by the PHP check and by the JS one alike.
  - **The tools and `configurators://schema` say where a picture key comes from:** the `path` of
    `media_get_file`, since `media_upload_from_url` answers with the id. `configurators_quote` says
    that `values` are resolved before `actions`, the schema reference that a size in `initial` is
    pulled into its range, and `configurators://catalog` what the block `configurator` takes.
  - **A tool argument's own description reaches the agent.** It was built as `$base + [...]`, which
    keeps the shared one: `configurators_quote` and `_create`, `tags_merge`, `articles_*` `slug` and
    the banner tools showed the generic text. Now the specific words win, after the shared format.

- Updated dependencies [b4296aa]
  - @webx-ui/module-media@0.12.0
