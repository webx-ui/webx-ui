---
'@webx-ui/php': minor
'@webx-ui/module-orders': minor
---

Orders: «Change the lines» in the card — an editor with the product search of «New order» and the
service's own dry run on every change (each line before → after, the totals, the balance, the
warnings), the reason required, «Message to the buyer» and «Tell the buyer». A paid order says what
the edit does to its payment before the save; after it a top-up or a refund with the sum over is
offered. An edit saved meanwhile in another tab reads the order again. «History» shows each edit,
«Summary» counts them, the list marks the order «Edited». MCP `orders_items_edit` (its dry run is
the editor's answer), `orders_history` with every edit, `orders_get` with `items_revision`.
`orders.manage`.
