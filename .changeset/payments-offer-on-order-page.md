---
'@webx-ui/php': minor
---

`webx-ui/module-payments`, `webx-ui/module-orders`: the page of an order on receipt offers «Pay online now». Such an order awaits nothing, so nothing led its buyer to the page of payment, the only place that had the list. `BlockDefinition` takes `offer`, HTML for an order that does not await payment and may still be paid, and the order's page shows it under the scale in `$order['offer']`; `webx:doctor` names a theme's copy of `order/body.blade.php` without it.
