---
'@webx-ui/module-orders': patch
'@webx-ui/php': minor
---

A cancellation moves the order into the first active status that cancels: `Orders::cancel()` writes the move in the same transaction — a history line with the reason, `OrderStatusChanged` and `OrderPublicStatusChanged` before `OrderCancelled`, the letters of the public status — so the buyer sees the order cancelled rather than «Accepted». `MilestoneMark::$move` is that move (null when the order stays); the panel's dry run, `orders_cancel` and the mass cancellation name the status. A site with no such status keeps the order where it stands, and `webx:doctor` says so (`Orders: cancellation`).

The card of an order: the totals count the lines and the units (the units given back too), the source of the order is a line of its own under «Placed», the reason of a cancellation and of each refund — with its reference — stand on a plate with a label in the totals and in «History». The status is changed with a pencil beside it and with «Change status» in the head beside «Mark as paid».
