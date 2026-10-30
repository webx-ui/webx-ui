---
'@webx-ui/core': patch
'@webx-ui/module-admin': minor
'@webx-ui/module-orders': minor
'@webx-ui/php': minor
---

«Orders» opened without a tab is on «New» while it holds any order and on «All» otherwise: the list asks `view=auto` and the answer says which (`view`), by the counts it sends anyway — one request, and no tab is lit before it is known. A tab in the address wins and stays there, «All» included.

Over the list stand the figures: orders and how many are paid, revenue (paid less given back), the average paid order and what awaits payment now, for today, 7 or 30 days — calendar days in the site's timezone, «Today» from midnight, the same in `orders_summary` — money per currency, with a bar a day for the last thirty under them. They come from `GET {api}/orders/summary` — `Panel\OrdersSummary`, the class that now answers the agent's `orders_summary` too, which gains `awaiting_by_currency`, the average of each period and `daily` — asked apart, so the rows never wait. The block folds; folded or not and the period are remembered for the manager.

A row of the list opens (`WxTable`'s expandable rows) to the lines with the totals of the card's «Summary» under them (paid or awaiting, given back, where the order came from), the buyer and the recipient, the buyer's comment, the pinned note and the latest one, the delivery and payment lines and the milestones — `GET {api}/orders/{id}/glance` on opening, kept for the page. Phones and e-mails across the orders are `tel:` and `mailto:` links that do not open the order when clicked; on a narrow list a card does not open and a tap goes to the order.

The manager note left the tab «Buyer» and is pinned over the card's «Notes»: a captioned plate changed in place, emptied to unpin; saved through `details` as before, it is the card's `order.manager_note` and no longer one of the screen's `values`. `orders_update` is unchanged.

`WxTable`'s opened row no longer clips what it holds at the cell's edge. `WxListScreen` takes a `before` slot — what is read under the head and over the views. `useDates()` gains `day()`, a calendar day without a clock.
