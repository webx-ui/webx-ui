---
'@webx-ui/php': minor
---

`webx-ui/module-configurator` on the site, and the configuration in a request of the inbox.

- **`<x-webx-configurator key="…" />`** — the server prints every step, option and size, the price
  of the start with «How the price is made», «from N» on the cards, the picture by the `previews`
  rules, a discount in force (its label and the old price struck out, gone after its last day) and
  the messengers of the site's contacts beside «Get a quote». The widget
  (`webx/configurator/configurator.js`, the npm engine built in, about 5 KB gzip) recounts as the
  visitor chooses. The published configurator is cached in the group `configurators`.
- **The base look** `resources/theme/configurator.css`: steps and a sticky price side by side from
  45rem of the configurator, one column with the price and the button stuck to the bottom under it;
  site tokens and classes only.
- **The offered block `configurator`** (`webx:blocks:offered --install`): the configurator, a
  heading (`-` — none) and `wx-configurator-preset` — where the block starts, picked from the
  configurator's published steps and sizes.
- **The request.** The radios and the sizes belong to the dialog's form by `form=`, so the
  configuration goes with the contacts with JavaScript and without it. The server prices it again —
  a price in the request is never read — and writes the selection, the sizes, the area, the lines,
  the discount and the total into the submission; a size outside its limits is a 422 under
  `configurator.values.<field>`, a configuration fastened to another form is refused.

`webx-ui/module-inbox`: enclosures — `Contracts\Enclosure` and `Submissions\Enclosures`, for a
module whose data goes with a form. The form names one with `webx_enclosure[]`; its errors come with
the errors of the fields; it is written as an answer with no field (its text in the letter,
Telegram, the export and `inbox_get`, its structure for the card), and it is part of the
submission's hash, so two configurations from one person are two submissions.
