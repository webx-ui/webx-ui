---
'@webx-ui/php': minor
---

Cart: the JSON API — `GET /api/cart`, `POST /api/cart/items` (a batch, all or nothing, with `added`), `PATCH` and `DELETE /api/cart/items/{id}` (a generated line's key declines it), `DELETE /api/cart` — every answer the whole state with `*_formatted` beside each amount; the `web` group with CSRF, a per-IP throttle on writes, `Cache-Control: no-store` and `Vary: Cookie`, a line of another cart answered 404, and a form without JavaScript sent back to its page with the `webx-cart` flash. The buy button `<x-webx-cart::add>` — a form with `@csrf` around it, `is-in-cart` from the server. Catalogue: products go into the cart through `catalog.product` (`ProductPurchasable`), registered only when the cart is installed and prices are on — a batch priced live in one go, a deleted product gone, a hidden one or one with its price on request not for sale; the product page's «Buy» button becomes the cart's.
