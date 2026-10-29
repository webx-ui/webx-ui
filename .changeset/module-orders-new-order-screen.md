---
'@webx-ui/php': minor
'@webx-ui/module-orders': minor
---

«New order» in the orders' panel (spec §29.2): a manager with `orders.manage` finds products by a word, picks a variant, sets the quantities and the buyer's contacts, sees the order's own totals and warnings counted live, and creates the order — `placeByManager()` with the source `panel`; the card and the list mark it «Created in the panel» and the list filters `source = panel`. `Orders::previewByManager()` takes `contacts: false` to count the lines alone. module-cart: the optional provider contract `FindsPurchasables` (`find()` → `PurchasableChoice` with `ChoiceOption`s) and `Purchasables::find()`, which asks only the providers that implement it. module-catalog: `catalog.product` is found by name, article number, barcode or id, the unpublished marked unavailable.
