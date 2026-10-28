---
'@webx-ui/php': minor
---

Cart: the new `webx-ui/module-cart` — a guest cart with its tables (`carts`, `cart_items`), a token in an httpOnly cookie (the database keeps its hash), the `Purchasable` contract with `ResolvesWithOptions` and `ChecksOptions`, the site's currency behind `CurrencySource` with `Money::format()` written by the currency's rules, batches written whole with bundles one level deep, the totals chain with five empty registries for satellites, `cart()` for Blade and `webx:cart:prune` on the schedule. No HTTP API, MCP or widget yet. Admin: the setup catalogue knows `cart` (off by default) and a module without an npm half.
