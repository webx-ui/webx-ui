# webx-ui/module-cart

A guest cart. Lines live in the database; the visitor is known by a token in an httpOnly cookie.
What a line holds and what it costs **now** the cart asks the owner of the record through the
`Purchasable` contract; it knows no table of any other module. Totals are a chain other modules
plug into. No page, no panel section: a JSON API, `cart()` and a buy button for Blade, reading
tools for agents, and (a later stage) a widget.

## What it owns

- **Tables** `carts` (`WebxUi\Cart\Models\Cart`: sha256 of the token, `locale`, `currency`,
  `declined_rules`) and `cart_items` (`CartItem`: `type`, `ref_id`, `options`, `qty`, `parent_id`,
  `name_at_add`, `price_at_add`, `added_at`). A child of a bundle counts its `qty` per unit of the
  parent; one level deep.
- **The contract** `WebxUi\Cart\Contracts\Purchasable::resolve(array $refs, string $locale)`: a
  batch of ids of one type → `PurchasableLine|null` each; null or left out — the record is gone.
  - **Build `PurchasableLine` with named arguments.** New fields only ever come last and
    optional; positional arguments break when one is added.
  - `price` is an integer in minor units; `0` is «price on request» and cannot be bought.
  - `names` (locale → name) feeds the `name_at_add` snapshot shown when the record is gone.
  - A provider whose price depends on the choice implements `ResolvesWithOptions`; one that
    accepts options at all implements `ChecksOptions`. Without it, non-empty options are refused.
- **The registry** `WebxUi\Cart\Purchasables`: `register('<module>.<entity>', Provider::class)`.
- **Currency** `Contracts\CurrencySource` (default `ConfigCurrency`, bound with `singletonIf`),
  `CurrencyFormat`, and `WebxUi\Cart\Money`: `format(int $minor, string $currency)` — the same
  string in every language — and `toMinor('49.90', 'EUR')` without floats.
- **The chain** `WebxUi\Cart\Totals\Calculator::calculate($cart, $locale, Purpose)` → `CartState`
  (`toArray()` puts `*_formatted` beside every amount). Five registries, empty here:
  `LineSources`, `AdjustmentSources`, `TenderSources`, `BenefitSources`, `HintSources`, each
  `register($source, int $priority = 0)` — lower runs first.
- **Writes** go through `WebxUi\Cart\CartService`: `add()` (a batch, all or nothing, `key` and
  `parent` for a bundle), `update()`, `remove()`, `clear()`. A refusal is a `ValidationException`.
- **`cart()`** (`WebxUi\Cart\CartHandle`, facade `WebxUi\Cart\Facades\Cart`): `count()`,
  `isEmpty()`, `has($type, $ref)` — no query without a cookie, one with it — and `state()`.
- **JSON API** (`routes/web.php`, `Http\CartController`, prefix `webx-cart.path`): `GET /api/cart`,
  `POST /api/cart/items` (a batch), `PATCH|DELETE /api/cart/items/{id}`, `DELETE /api/cart`. Every
  answer is `CartState::toArray()`; the `web` group (CSRF), `throttle:webx-cart` on writes,
  `Cache-Control: no-store` and `Vary: Cookie` (`Http\KeepPrivate`). A request that does not ask
  for JSON is a form: redirect back with the `webx-cart` flash. A line of another cart is 404;
  a generated line's key (`rule#12:0`) declines it (`CartService::decline()`).
- **The buy button** `<x-webx-cart::add type="…" :ref="…" />`: a form with `@csrf` around a
  `button.webx-cart-add` (`is-in-cart` from `cart()->has()`, `data-webx-cart-add`, `-qty`).
- **Command** `webx:cart:prune [--days=] [--dry-run]`, on the schedule daily.
- **Doctor**: a line when the site has no currency (the cart then refuses every write).
- **MCP** (`src/Mcp`, module `cart`, registered only where `module-admin` and `webx-ui/mcp` are):
  `cart_list` (a page of carts, sums at `price_at_add`, filters `updated_since`, `updated_before`,
  `contains`, `min_units`), `cart_get` (the calculator's state plus dates and `meta`),
  `cart_summary` (active, abandoned, records in most carts), `cart_quote` (a `POST` batch rehearsed
  in a rolled-back transaction: the state or the refusals); resource `cart://catalog` (types, the
  chain's sources, settings, `CartState::LINE_FIELDS`, warning codes). Reading only.

### Permissions

`cart.view` — every `cart_*` tool and `cart://catalog`; there is no `cart.manage` (nothing writes).
No route asks for a permission: every route is the visitor's own cart — the cookie's token opens
only its own, a line of another cart is 404 — and is named as open with that reason in
`tests/AccessCoverageTest.php`. A new route asks for a permission or joins that list with its
reason.

## Change it without forking

| You want                              | Do this                                                                                    |
| ------------------------------------- | ------------------------------------------------------------------------------------------ |
| Sell something of your module         | implement `Purchasable`; in your provider's `boot()`, guarded by `class_exists`, see below |
| The site's currency                   | `WEBX_CART_CURRENCY=EUR`; empty — the catalogue's `WEBX_CATALOG_CURRENCY`                  |
| Another way of writing amounts        | bind your own `WebxUi\Cart\Contracts\CurrencySource` in `register()`                       |
| A discount, a gift, a payment, a hint | implement the link's `*Source` and register it in its registry with a `priority`           |
| The cookie's name or lifetime         | `WEBX_CART_COOKIE`, `webx-cart.cookie.days`                                                |
| Keep carts longer, or never prune     | `webx-cart.prune_after_days` — a number of days, or `null`                                 |
| Limits                                | `webx-cart.batch_max`, `webx-cart.qty_max`, `webx-cart.lines_max`                          |
| Another prefix of the API             | `WEBX_CART_PATH`; `webx-cart.throttle` — writes a minute per IP                            |
| A buy button in a view                | `<x-webx-cart::add type="catalog.product" :ref="$product->id">Buy</x-webx-cart::add>`      |
| The counter in the header             | `<span data-webx-cart-count>{{ cart()->count() }}</span>`                                  |
| Other words of the widget or errors   | `php artisan vendor:publish --tag=webx-cart-lang`                                          |
| The config file                       | `php artisan vendor:publish --tag=webx-cart-config`                                        |

Registering a provider, so the module works with or without the cart:

```php
if (class_exists(\WebxUi\Cart\Purchasables::class)) {
    $this->callAfterResolving(\WebxUi\Cart\Purchasables::class, static fn ($cart) =>
        $cart->register('catalog.product', ProductPurchasable::class));
}
```

## Do not

- Do not write `cart_items` or `carts` yourself: the batch checks, the merging of equal lines and
  the bundle's qty live in `CartService`. Call it.
- Do not store a discount anywhere. It is computed from the current lines by an
  `AdjustmentSource` on every count; a stored one goes stale the moment a price moves.
- Do not format an amount in a template or a script (`number_format`, `toLocaleString`). Print
  the `*_formatted` string of `CartState::toArray()` or call `Money::format()`.
- Do not use floats for money. `Money::toMinor()` reads a decimal string.
- Do not ask a provider per line. `resolve()` takes a batch; the calculator asks once a type.
- Do not give a generated line a price of 0: the constructor refuses it. A gift costs at least 1.
- Do not write `<x-webx-cart::add>` in a view that must compile without the cart: Blade looks a
  component tag up at compile time. Use `<x-dynamic-component component="webx-cart::add">`.
- Do not show or log the cart's token. The database keeps only its hash; no MCP answer has it.
- Do not add a tool that writes a cart. The visitor changes it; `cart_quote` rehearses instead.

## Check your work

- `php artisan webx:doctor` — no «Cart: no currency» line.
- `php artisan webx:cart:prune --dry-run` counts the carts it would delete.
- In a template, `cart()->state()->toArray()` shows your lines with `available: true` and their
  live price; a deleted record shows `gone: true` with its snapshot name.
- `curl -s https://<site>/api/cart` answers an empty state and sets no cookie.
- `php artisan webx:mcp-tools` lists `cart_list`, `cart_get`, `cart_summary`, `cart_quote`.
- `vendor/bin/phpunit vendor/webx-ui/module-cart/tests` in the monorepo — the batch, the bundle,
  the chain and the formats.

## Read more

- [README.md](README.md) in this directory; the guide: https://github.com/webx-ui/webx-ui/blob/main/apps/docs/guide/cart.md
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_CART.md
- The commerce map: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_COMMERCE.md
