---
'@webx-ui/php': minor
---

Orders: the buyer reads the extra fields. «Thank you», the signed page of the order and «My orders» print the snapshot after the delivery and the payment, a `webx-order__fact webx-order__fact--field webx-order__fact--field-<key>` each, the text of several lines with its line breaks. The events of the order carry `fields` — the rows with `key`, `label` and `value`, the text the buyer read — and `field.<key>`, one field's text, empty when the order has none. The default templates of the e-mail, Telegram and plain channels print `{{#fields}}` after the delivery and the payment; the channels a site already saved are not changed — add the loop yourself. A theme with its own copy of `order/body.blade.php` adds the loop over `$order['fields']` to show them.
