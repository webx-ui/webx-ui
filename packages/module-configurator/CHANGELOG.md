# @webx-ui/module-configurator

## 0.2.2

### Patch Changes

- f85efda: Every link may be a file of the media library, and the file field looks like the link field.

  - **A file on any link.** `files` is now on by default — on `WxLinkField`, `WxLinkPicker` and `LinkType::rules()` alike — so every button, menu item, banner and form link offers «A file» next to «This site», «An address» and «Nowhere». A node that has to be a page says `props.files: false`: the configurator's page and the region preview do. This changes what the server accepts: `target: "file"` is taken by every `wx-link` and by every caller of the rules with no node, `menu_add_link`/`menu_update_link` included.
  - **Menu items keep a file.** `menu_items` gets `path` and `download` (migration `2026_11_26_000000_add_file_to_menu_items`, reversible). The site prints the library's address with `download="<name>"` when ticked, and calls an item with no label of its own by the file's name; a file gone from the library takes the item out of the menu, like a draft page, and deleting a library file forgets the built menus. `MenuLink` has `download`; the MCP tools take `target: file` with `path` and `download` and refuse a key the library does not have.
  - **The redirect and the thank-you button of a form keep a file** — they used to drop the key on the way to the site.
  - **`wx-file` is drawn as a row and a panel**, the way a link is, on the new shared `WxFieldRow` of `module-admin`: the glyph (a thumbnail for a picture), the upload name and «PDF · 61 kB», a clear button beside it; the panel chooses from the library, uploads straight into it, edits the caption (`alt` only with `accept: image`), and with `props.download: true` offers «Download instead of opening» — the site then reads `download` as the upload name. The stored value is unchanged, so every saved value opens as it is. A link to a file now says the file's name and size on its row too (`PickedFile` has `size`).

- Updated dependencies [f85efda]
- Updated dependencies [f85efda]
  - @webx-ui/core@0.49.1
  - @webx-ui/module-admin@0.39.0
  - @webx-ui/module-media@0.14.0

## 0.2.1

### Patch Changes

- 8bc2392: `WxLinkField` — the `wx-link` field of a described screen — is one row now: what the link points at in two lines (the entity's name, its section and today's address with the anchor; the host and the full address; "No link"), badges for a new tab and each `rel` that is on, and `WxLinkPicker` itself, unchanged, in a panel under the row. The panel is a `WxPopover`: teleported, as wide as the row, opening upwards when there is no room below, closed by Escape, a click outside or "Done". The value, its validation and what the server keeps are what they were. The menu item's "Where it points", the form's redirect and thank-you button link (inbox) and the configurator's page use it. New props: `placeholder`, `label`; events `open`, `close`. Core gets a `ban` icon; module-admin gets the field's five words in every language.

  Escape in a popover of a block's form or of a site-bar screen — the link field's panel, a select — closes that popover only, not the block's form or the drawer.

- Updated dependencies [8bc2392]
  - @webx-ui/core@0.49.0
  - @webx-ui/module-admin@0.38.0
  - @webx-ui/module-media@0.13.1
  - @webx-ui/schema@0.11.3

## 0.2.0

### Minor Changes

- 764d8dd: Every code somebody types is made, not refused (#125, stage 2). The trait `HasCodes` of `localization` brings a model's code columns to their shape on every save, and `Model::codeFor()` does the same for a request before its own check. That covers the panel, MCP, the import and a plain `save()`, and only an empty or a taken code is refused. `Slug::code()` takes `Slug::BOTH` / `Slug::BOTH_UNDERSCORED` for a key that takes `-` and `_`, and a `$lead` for a code that must start with a letter. `wx-code` and `WxCodeField` take `separator` `-_` / `_-`, `lead` and `localized`. Converted fields: label, property code and toggle slug, collection key, block slug, role slug, task project slug, menu key, banner place key, form slug, form field name, inbox status key, public order status code, checkout field key, user group code, delivery point kind, configurator key and the keys of its schema. «тормозные колодки» becomes `tormoznye-kolodki` in every one of them. `CodeCoverageTest` and `codeCoverage.test.ts` fail on a new code field that skips this.

### Patch Changes

- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
- Updated dependencies [764d8dd]
  - @webx-ui/module-admin@0.37.0
  - @webx-ui/core@0.48.0
  - @webx-ui/module-media@0.13.0
  - @webx-ui/schema@0.11.2

## 0.1.3

### Patch Changes

- cd801c7: Покрытие справки панели включено: `AssertsHelp` и `HelpCoverageTest` в каждом пакете с экраном, патчем или разделом, `HelpPackagesCoverageTest`, `scripts/help-coverage.test.mjs`, команда `webx:help --check`, гайд `panel-help`. Всё, что ещё без текста, перечислено в `help-pending.json` и вычёркивается волнами наполнения. Пакеты регистрируют `resources/help`, у всех маршрутов панели есть `meta.help`, формы в панели списка передают ключ своей шапке. Кнопка появится, когда у экрана будет текст.
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
  - @webx-ui/core@0.47.0
  - @webx-ui/module-admin@0.36.0
  - @webx-ui/module-media@0.12.3
  - @webx-ui/schema@0.11.1

## 0.1.2

### Patch Changes

- Updated dependencies [9c212d0]
- Updated dependencies [9c212d0]
- Updated dependencies [9c212d0]
- Updated dependencies [9c212d0]
  - @webx-ui/module-admin@0.35.0
  - @webx-ui/core@0.46.1
  - @webx-ui/schema@0.11.0
  - @webx-ui/module-media@0.12.2

## 0.1.1

### Patch Changes

- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
  - @webx-ui/core@0.46.0
  - @webx-ui/module-admin@0.34.0
  - @webx-ui/schema@0.10.0
  - @webx-ui/module-media@0.12.1

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
