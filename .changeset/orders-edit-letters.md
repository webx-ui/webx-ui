---
'@webx-ui/php': minor
'@webx-ui/module-orders': patch
---

Orders: the buyer hears of an edit. `orders.items_changed` — «Order changed» with what came and
went, the total before, the balance and the manager's message (never the reason) — goes when the
edit's «Tell the buyer» is on; `orders.payment_requested` — «Order N: X to pay» — is sent by hand
while the order owes, greyed with «The order owes nothing» once it does not. Both have a channel to
the buyer in the starting set, made by a migration on sites set up before. The buyer's page of the
order says «Items changed on …», hides the lines taken out and shows «Left to pay» or «We will give
you back» under the money.
