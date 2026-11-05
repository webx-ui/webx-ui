---
'@webx-ui/php': minor
---

`webx-ui/module-payments`: the marks of the payment systems, with nothing changed for the buyer yet. `Contracts\ProvidesPaymentMarks` lets a type say which systems a method takes, `PaymentMarks` is the registry a satellite adds to, and `MethodOption` carries `marks` and `applePayOnly` (`marks` and `apple_pay_only` in `GET /api/payments/methods`). Visa, Mastercard, Apple Pay and Google Pay ship as SVG cards based on Simple Icons, served with a cache for good from `webx/payments/marks/{key}.svg`; `resources/marks/NOTICE.md` says whose the trademarks are. A mark without a file is a text plate, and `<x-webx-payments::mark>` draws both. A file is replaced without code: only its `viewBox` is read. `webx:doctor` names a mark nobody registered.
