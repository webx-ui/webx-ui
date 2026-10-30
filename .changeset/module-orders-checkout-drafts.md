---
'@webx-ui/php': minor
'@webx-ui/module-orders': patch
---

module-orders: the checkout's groundwork — the switch `webx-orders.checkout.enabled` (`WEBX_ORDERS_CHECKOUT`, on by default) with the `checkout` section of the config, the table `order_checkout_drafts`, `webx:orders:checkout-prune [--dry-run]` on the daily schedule, the dictionary group `checkout.*`, and the tab «Checkout» of the order settings behind the new permission `orders.checkout.settings` (contact fields, recipient, comment, terms). Off — no command, no tab, no draft carried at sign-in. module-cart: `Events\CartsMerging(guestCartId, accountCartId)`, dispatched inside the sign-in merge before the guest's cart goes; module-orders moves the guest's checkout draft to the account's cart with it.
