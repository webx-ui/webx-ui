---
'@webx-ui/php': minor
---

Cart: the guest's cart joins the visitor's account. A new column `carts.user_id` (unique, no foreign key); a signed-in visitor's cart is found by the account, the cookie opens only a guest's. On the panel's `VisitorSignedIn` the guest's lines are added to the account's cart — equal lines add up within `qty_max` and the provider's `maxQty`, bundles come over whole — the guest's cart goes and the cookie gets a new token; `VisitorSignedOut` drops the cookie, `VisitorDeleting` deletes the account's cart. `cart_list` and `cart_get` show `user_id`. The cart still requires neither the panel nor a visitors module.
