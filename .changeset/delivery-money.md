---
'@webx-ui/php': minor
---

Cart: `WebxUi\Cart\BenefitKeys` — the registry of benefit keys modules offer to cart rules (`register($key, $label)` with a label by locale or a dictionary key, `has()`, `keys()`, `options($locale)`; a malformed or repeated key throws, a key nobody registered still works in the chain); `cart://catalog` lists them in `benefit_keys`.

Delivery: money in the cart's chain (delivery spec §9). `DeliveryAdjustments` — priority 1000, after every discount and before the wallet — adds the chosen method's `fixed(N > 0)` price as the adjustment `delivery` (`meta.kind = delivery`) on the checkout and the order, never in the cart's widget; «Free» and «By carrier rates» add nothing. `shipping.paid_by_store` is registered in `BenefitKeys` and turns the price of every method that honours it into `free(was)` (`Quote::paidByStore()`). `Quotes` caches a satellite's quote for `webx-delivery.quotes.ttl` and falls back to «By carrier rates» when it throws; making an order neither reads the cache nor falls back (`QuoteFailed`). The buyer's choice is the table `delivery_selections`; `Delivery::cost()` gives the totals line «Delivery cost», `Delivery::quoteFor()` the price of a selection, `Methods::option()` one method of a locality.
