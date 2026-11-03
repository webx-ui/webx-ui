---
'@webx-ui/php': minor
'@webx-ui/module-payments': minor
'@webx-ui/core': patch
---

Payments, stage P8: an online method in the checkout opens its attempt right after the order and sends the buyer to the gateway (`PaymentsCheckoutSlot::next()`, never throws; `payAgainUrl()` — the signed page of payment while the order awaits its money). The tab «Payment» captures money held — all of it, or a part where the gateway takes one — or releases the hold, each with a `dry_run` line first (`POST …/orders/{order}/attempts/{payment}/capture|void`, `orders.manage`); MCP `payments_capture` and `payments_void`. The order's bank details say «Purpose of payment» without the form's hint; the journal's «Signature invalid» stays on one line, its filters reset with «Reset filters». Orders: the checkout's antispam mark is the session's first drawing of the confirmation, so the column drawn again after a choice no longer refuses the first «Place the order». Core: `WxDatePicker` and `WxDateRangePicker` teleport their calendar into the dialog or popover they sit in — a day clicked in a modal dialog is chosen instead of closing the calendar.
