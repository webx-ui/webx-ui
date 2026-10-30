---
'@webx-ui/php': minor
---

module-catalog: «Buy in one click» of `webx-ui/module-orders` on the product page, beside the cart's button — printed while `orders` is a registered module (`Orders\QuickButton::shown()`, the registry rather than `class_exists`) and shown while the checkout and `orders.checkout.quick.enabled` are on; a dynamic component, so a site without the orders compiles the view and keeps the cart's button alone, and `module-orders` stays out of `require` (a `suggest`). The button is the point `catalog.product.quick` (`webx-catalog::points.product-quick`): a theme removes it with an empty `views/parts/catalog/product/quick.blade.php` or includes the partial elsewhere. Both buttons get the same `options` — the chosen variant a site's view composer gives to `webx-catalog::product`.
