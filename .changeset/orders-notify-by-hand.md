---
'@webx-ui/module-orders': minor
'@webx-ui/module-notifications': minor
'@webx-ui/php': minor
---

A message about an order is sent again by hand (decision 99): «the buyer says the letter never came». A line of «Notifications» on the tab «History» — sent or failed — has **Send again**: the same message to the same address, asked first with the letter's name and the addressee, as a new row of the log marked «Sent by hand» with who sent it. **Send the buyer a letter** in the `···` of the card picks an event the order has a reason for (placed, the public status it stands in, paid, payment failed, received, cancelled, refunded), shows each channel's letter from a dry run and sends it to the order's e-mail as it is now — only through the channels of the event that write to the buyer; with no such channel, or no e-mail, the action is greyed with why. Each send is a history line `notification_sent` with its author; the same send again within a minute is refused, so a second press sends nothing. It is `orders.manage`, and there is nothing of it without `webx-ui/module-notifications`. `Orders::notify()` and MCP `orders_notify` (with `dry_run`) do the same.

`webx-ui/module-notifications`: `Notifications::resend()` sends a row of the log again — one that went too, unlike `retry()` — as a new row; `sendByHand()` sends an event again to its addressee alone; `addressed()`, `writesTo()` and `render()` tell a consumer which channels would send and what. A new migration adds `manual`, `manual_by` and `resent_from` to `notifications_deliveries`; the log of the section marks such rows «Sent by hand» with who sent them.
