---
'@webx-ui/php': minor
'@webx-ui/module-payments': minor
---

`webx-ui/module-payments`: `GatewayStatus` gets `declined` — the bank turned the card down and the gateway keeps the payment open for another try. The buyer reads «The payment did not go through: the bank declined the card» with the reason, «Pay again» and «Choose another method» instead of «We are checking»; the attempt stays `pending` to its deadline, a late payment counts, the page stops refreshing itself, and `/api/payments/{uuid}/status` answers `declined`. «Choose another method» is one button over every method of the order by its sum and delivery, on receipt and bank transfer too. An order awaiting an online payment says «Awaiting payment» on its page with «Pay» and «Choose another method» (`BlockDefinition::$prompt`, `Orders::closeNote()` in `webx-ui/module-orders`). A capture or a release the gateway refused reads «Capture refused» / «Release refused» in the journal and tells the gateway's reason in the panel; the note «Money held …» leaves the notes when the hold is over. `webx-ui/payment-liqpay`: `try_again` answers `declined`.

**Behaviour change for themes:** a theme that overrides `webx-orders/order/body.blade.php` has to add the block `prompt` (`$order['prompt']['title']` and `$order['prompt']['html']`), or the buyer reads the old status with no buttons to pay; one that overrides `webx-payments/pay/page.blade.php` has to handle the state `declined`, or the buyer the bank turned down reads nothing. `webx:doctor` names such a copy; the payments guide says what to add.
