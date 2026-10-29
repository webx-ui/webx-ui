---
'@webx-ui/php': minor
---

module-orders: the checkout page, without neighbours — `/checkout` (the route `checkout` the cart's button looks for, also behind the language prefix), `POST /checkout/steps/{step}`, `GET /checkout/summary` and `POST /checkout/place`, `no-store` and `Vary: Cookie`. The registry of the steps `Checkout\Steps\CheckoutSteps` with the contract `Step` and the steps «Contacts» (buyer and recipient, fields as the tab «Checkout» says, a signed-in visitor's contacts filled in) and «Comment», `CheckoutContext` (the visitor's cart counted once with `Purpose::Checkout`), `PlaceOrderBuilder`, the column of the totals with the agreement (notice or checkbox), the warnings of lines that cannot be bought and the page of an empty cart. Every step is a plain form: an order is placed without JavaScript; a step answers JSON to the widgets' form shell. Showing the page makes no draft.
