---
'@webx-ui/module-configurator': minor
---

New package `@webx-ui/module-configurator` — the section «Configurators» of the panel.

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
