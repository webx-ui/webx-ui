---
'@webx-ui/php': patch
---

`webx-ui/module-orders`, `webx-ui/module-payments`: «My orders» says «Awaiting payment» on an order the buyer can pay online, by the rule of its page, one query for the list (`BlockDefinition::$awaiting`, `PayPage::promptable()`); paid, held and offline orders keep the public status. The note «The gateway refused to capture …» leaves the order's notes with the hold — captured, released or expired — and stays in the history. `webx:doctor` warns of a theme's `order/body.blade.php` without the block `prompt` and `pay/page.blade.php` without the state `declined` (`Doctor\OverriddenViewsCheck`).
