---
'@webx-ui/php': minor
---

`webx-ui/payment-liqpay` reads the notifications of LiqPay: the signature of `version: 7` with SHA3-256 for our requests, a notification checked on its raw `data` with SHA3-256 or SHA1, the table of statuses, and a thin client through `GatewayHttp`. The recorded answers moved into the package's tests. The page and the poll are still to come, so the method stays switched off.
