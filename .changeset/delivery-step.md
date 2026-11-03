---
'@webx-ui/php': minor
---

Delivery: the checkout's step (delivery spec §12). The JSON API `api/delivery/…` — `countries`, `localities`, `localities/popular`, `points` (a page of 20 searched inside the town), `points/{id}`, `streets` (public, cached by the browser for five minutes, 60 a minute per IP) and `methods`, `GET|PUT selection` (the visitor's cart, CSRF; a refusal by field writes nothing, a form without JavaScript goes back to its page). `<x-webx-delivery::step>` with its parts `country-picker`, `locality-picker` (the card of the town and the dialog «Where to deliver the order?» with the search and the popular towns), `methods`, `point-picker`, `address-form`, `summary-line`; `delivery.js` writes each change and draws the step again, and without JavaScript the step is one form. `DeliveryCheckoutSlot` fills the checkout's slot «delivery» of `module-orders` by itself: the step, its one-line summary, the line «Delivery cost», the choice for payment, the checks of the confirmation and the order's block.

Widgets: `<x-webx-select searchable>` — a list with a search: the options loaded filtered at once, the server asked for the rest (`source`, JSON `{options, more}`, «Show more»), the keyboard of a combobox; `:strict="false"` — a text field with suggestions. Without JavaScript a plain `<select>` (or the text field).
