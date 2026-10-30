---
'@webx-ui/module-orders': minor
'@webx-ui/php': minor
---

A refund recorded by mistake is reversed, never edited (decision 98): `Orders::reverseRefund()` marks the record reversed — when, from where, who and why — and keeps it in the history, takes what it gave back off the order (`refunded_total`, the tenders' `refunded_amount`, the lines' `refunded_qty`; the tenders a cancellation gave back stay), and takes the milestone «refunded» off when the order is no longer given back whole, with a history line. `OrderRefundReversed` goes after the commit; another order's record and one reversed already are refused (`RefundReversalRefused`), and a dry run answers what is left after it and whether the milestone comes off. A new migration adds the columns of the reversal to `order_refunds`.

The panel reverses a record from its plate in the totals and from its line in «History» (`orders.refund`): the dialog asks for the reason and shows the service's dry run, then offers to record the right refund. A reversed record stays struck through with its reason and no longer counts in «Given back». MCP `orders_refund_reverse` answers what the dialog does; `orders_get` and `orders_history` show the reversal.
