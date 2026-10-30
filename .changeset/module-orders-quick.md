---
'@webx-ui/php': minor
'@webx-ui/module-orders': patch
---

module-orders: «Buy in one click» (spec §29). `<x-webx-orders::checkout.quick-button type= ref= />` beside «Buy» opens a dialog — a phone, a name if given, the agreement as the tab «Checkout» asks — that posts to `POST /checkout/quick` (`Checkout\Http\QuickController`, no CSRF: the product's page may be cached whole). `Checkout\QuickOrders` puts the line into a service cart no cookie carries (`CartService::add(null, …)`), places the order of it with `source = quick` and clears that cart — one transaction, the buyer's cart and checkout draft untouched; an order without a name is «Buyer» in its language. Antispam: the mark of time, the honeypot, the origin, 3 orders an hour an address and a phone (`webx-orders.checkout.throttle.quick_per_hour`); the same dialog twice within ten minutes is one order. Settings `orders.checkout.quick.enabled` (off) and `orders.checkout.quick.message` (a text a language) on the tab «Checkout». The list of orders filters by `source` (the milestone «placed»; `orders_list` too), a one-click order carries the mark «One-click order» in the list and the card.
