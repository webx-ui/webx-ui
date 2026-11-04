---
'@webx-ui/php': minor
---

`webx-ui/module-payments`: `GatewayStatus` gets `notStarted` — the gateway has no such payment because the buyer never began it. The attempt stays `pending` to its deadline and a late payment counts, but the page of payment says «Payment not completed» with «Pay again» and «Choose another method» instead of «We are checking», and `/api/payments/{uuid}/status` answers `not_started`. «Choose another method» is also on the page after a failure and with bank details, as the spec promised. Gateway A gets the button «Leave without paying»; `GatewayStatus` refuses `notStarted` with any status but `Pending`.
