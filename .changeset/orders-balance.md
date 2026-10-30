---
'@webx-ui/module-orders': minor
'@webx-ui/php': minor
---

An order keeps its balance (spec §36, decision 102): the money that came in (`orders.paid_total`) and the stored `balance` — what is still due less what is over — written by every method of `Orders` that moves one of its terms, in the same update. A refund never makes an order owe. `Orders::balance()` answers it as `Balance`; `mark(paid)` takes the amount a gateway got; `recordPayment()` records a top-up of a paid order — a history line `payment` and `OrderPaymentReceived`, never a second «paid»; `awaitPayment()` is the pair of `stopAwaitingPayment()`. A refund may give back what came in when that is more than the order costs, and the revenue counts the money that came in. A migration adds the columns and the index `(balance, placed_at)` and fills them for the orders there are.

The panel shows «Paid» and the balance in the card's «Summary» and in an opened row, a badge under the total and the filter «Payment differs» in the list, the orders waiting for a top-up over it, and «Record a top-up» on the card. MCP `orders_payment_record` answers what the dialog does; `orders_get`, `orders_list` and `orders_summary` carry the balance.
