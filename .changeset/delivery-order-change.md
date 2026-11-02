---
'@webx-ui/php': minor
'@webx-ui/module-delivery': minor
'@webx-ui/module-orders': minor
'@webx-ui/module-admin': minor
---

The delivery in the order's card: the price of delivery is a row of its own in «Summary» («Delivery» between the discounts and the total; «Free» and «By carrier rates» in words), and «Items» no longer repeats the delivery block or shows its raw data — a block registered with `view` lives on its own tab. «Change delivery» on the tab «Delivery» (and `delivery_order_change` for agents, with `dry_run`): the town, the method, the point or the address checked as at the checkout, a new snapshot, the price in place of the old one with the total and the balance counted again, a history line «was → is»; behind `orders.manage`, not while a waybill stands. `module-orders`: `Orders::changeBlock()`, `BlockDefinition` `charge`, `price` and `view`, the history kind `block_changed`. `module-admin`: `HistorySubject.reload` — a node patched onto a screen asks its host to read the record again.
