---
'@webx-ui/php': minor
---

module-orders: an order a manager places (spec §29.2, decision 85). `Orders::placeByManager(ManagerOrder, source = 'panel')` puts the manager's lines into a service cart no cookie carries (`CartService::add(null, …)`), places the order of it through `place()` — today's prices and the cart's rules, the next number, the events and letters of any order — and clears that cart, one transaction; the milestone «placed» and the first line of the history keep the source and the administrator. `previewByManager()` counts the same lines for the order and rolls them back (`ManagerPreview`). The tool `orders_create` (`orders.manage`) wraps it with the source `mcp`; its `dry_run` answers the lines, totals and warnings of the cart and writes nothing.
