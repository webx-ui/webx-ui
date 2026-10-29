# webx-ui/module-cart

A cart. Lines live in the database; a guest is known by a token in an httpOnly cookie, a signed-in
visitor by `carts.user_id`, and the guest's cart joins the account's at sign-in.
What a line holds and what it costs **now** the cart asks the owner of the record through the
`Purchasable` contract; it knows no table of any other module. Totals are a chain other modules
plug into. No page, no panel section: a JSON API, `cart()` and a buy button for Blade, a widget
(a drawer or a dialog, and a mini cart under the icon), and reading tools for agents.

## What it owns

- **Tables** `carts` (`WebxUi\Cart\Models\Cart`: sha256 of the token, `user_id` — unique, no FK,
  null for a guest — `locale`, `currency`, `declined_rules`) and `cart_items` (`CartItem`: `type`, `ref_id`, `options`, `qty`, `parent_id`,
  `name_at_add`, `price_at_add`, `added_at`). A child of a bundle counts its `qty` per unit of the parent; one level deep.
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
- **Writes** go through `WebxUi\Cart\CartService`: `add()` (a batch, all or nothing; `key` and `parent` for
  a bundle), `update()`, `remove()`, `clear()` (the row too), `empty()` (lines and declined rules go, row and
  token stay) — each under `lock()` of the cart's row. A refusal is a `ValidationException`; a batch's a `CartRefusal` (`atLimit`).
- **`cart()`** (`WebxUi\Cart\CartHandle`, facade `WebxUi\Cart\Facades\Cart`): `count()`,
  `isEmpty()`, `has($type, $ref)` — no query without a cookie, one with it — and `state()`.
- **Accounts** (`Accounts\AccountCarts` on the panel's `Visitor*` events, not `module-users`): a signed-in
  cart by `user_id` (`CartOwner`), the cookie opens only a guest's. Sign-in merges the guest's lines in
  (capped at `qty_max`/`maxQty`, bundles whole), re-keys the cookie; sign-out drops it; deleting deletes.
- **JSON API** (`routes/web.php`, `Http\CartController`, prefix `webx-cart.path`): `GET /api/cart`,
  `POST /api/cart/items` (a batch), `PATCH|DELETE /api/cart/items/{id}`, `DELETE /api/cart`. Every
  answer is `CartState::toArray()`; the `web` group (CSRF), `throttle:webx-cart` on writes,
  `Cache-Control: no-store` and `Vary: Cookie` (`Http\KeepPrivate`). A request that does not ask
  for JSON is a form: redirect back with the `webx-cart` flash. A line of another cart is 404;
  a generated line's key (`rule#12:0`) declines it (`CartService::decline()`). A refused `POST`
  into an existing cart is 422 with the errors **and** the state (and the panels); a line already
  at its maximum gets the notice `widget.max_reached` there, a form goes back with the cart open.
- **The buy button** `<x-webx-cart::add type="…" :ref="…" />`: a form with `@csrf` around a
  `button.webx-cart-add` (`is-in-cart` from `cart()->has()`, `data-webx-cart-add`, `-qty`).
- **The widget** `<x-webx-cart mode="drawer|dialog" preview="dropdown|none" />`
  (`View\Components\Widget`, defaults `webx-cart.widget.mode` and `.preview`; anything else throws):
  a trigger with the server's counter, the main view — the widgets' `<x-webx-dialog drawer>` or
  `<x-webx-dialog>`, where lines change — and the mini cart, a `<x-webx-dropdown>` with the lines,
  the total, «Edit cart» and the checkout. The icon opens the mini cart, on a header under 640 px
  or in the mobile menu the main view; a buy button and `open()` always open the main view. The
  script (`dist/cart.js`, ≤ 6 KB gzip, served at `{webx-cart.script_path}/cart.js`) fills them from
  `GET /api/cart` with `X-Webx-Cart-View: <mode>` and `X-Webx-Cart-Preview: dropdown`, answered as
  `html` and `preview_html` (`Rendering\Panel`, views `widget`, `widget.lines|line|totals|empty`). `window.webx.cart`: `add(items)`, `remove(id)`, `setQty(id, qty)`,
  `open()`, `state()`; event `webx:cart:change` (`detail.state`); any `[data-webx-cart-add="type:ref"]`
  (`-qty`, `-qty-from`, `-options`, `-open="false"`) is a buy button. After a form without JavaScript
  the page prints the main view open. Points: `parts/cart/line/after.blade.php`,
  `parts/cart/totals/before.blade.php`, `parts/cart/totals/after.blade.php`, `parts/cart/footer.blade.php`.
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
`tests/AccessCoverageTest.php`. A new route asks for a permission or joins that list with its reason.

## Change it without forking

| You want                               | Do this                                                                                             |
| -------------------------------------- | --------------------------------------------------------------------------------------------------- |
| Sell something of your module          | implement `Purchasable`; in your provider's `boot()`, guarded by `class_exists`, see below          |
| The site's currency                    | `WEBX_CART_CURRENCY=EUR`; empty — the catalogue's `WEBX_CATALOG_CURRENCY`                           |
| Another way of writing amounts         | bind your own `WebxUi\Cart\Contracts\CurrencySource` in `register()`                                |
| A discount, a gift, a payment, a hint  | implement the link's `*Source` and register it in its registry with a `priority`                    |
| The cookie's name or lifetime          | `WEBX_CART_COOKIE`, `webx-cart.cookie.days`                                                         |
| Keep carts longer, or never prune      | `webx-cart.prune_after_days` — a number of days, or `null`                                          |
| Limits                                 | `webx-cart.batch_max`, `webx-cart.qty_max`, `webx-cart.lines_max`                                   |
| Another prefix of the API              | `WEBX_CART_PATH`; `webx-cart.throttle` — writes a minute per IP                                     |
| The main view, the mini cart, checkout | `WEBX_CART_WIDGET=dialog`, `WEBX_CART_PREVIEW=none`; `WEBX_CART_CHECKOUT_URL` or a route `checkout` |
| The widget's markup or icon            | `views/vendor/webx-cart/widget/*.blade.php`, `icons/cart.blade.php` in the theme or the site        |
| A buy button in a view                 | `<x-webx-cart::add type="catalog.product" :ref="$product->id">Buy</x-webx-cart::add>`               |
| The counter in the header              | `<span data-webx-cart-count>{{ cart()->count() }}</span>`                                           |
| Other words of the widget or errors    | `php artisan vendor:publish --tag=webx-cart-lang`                                                   |
| The config file                        | `php artisan vendor:publish --tag=webx-cart-config`                                                 |

### Classes

The theme's contract (THEMES §10), in `resources/theme/cart.css`; restyle with the same class:
`webx-cart` (+ `webx-cart--drawer`, `webx-cart--dialog`, `webx-cart--preview`), `webx-cart__trigger`,
`webx-cart__icon`, `webx-cart__label`, `webx-cart__count` (`is-empty`), `webx-cart__dialog`, `webx-cart__panel`
(+ `webx-cart__panel--preview`), `webx-cart__lines` (+ `webx-cart__lines--children`), `webx-cart__line` (`is-unavailable`),
`webx-cart__line-image`, `webx-cart__line-body`, `webx-cart__line-name`, `webx-cart__line-options`,
`webx-cart__line-price`, `webx-cart__line-old`, `webx-cart__stepper`, `webx-cart__stepper-button`,
`webx-cart__stepper-input`, `webx-cart__remove`, `webx-cart__warning`, `webx-cart__notice`, `webx-cart__totals`,
`webx-cart__total-row`, `webx-cart__payable`, `webx-cart__hints`, `webx-cart__edit`, `webx-cart__checkout`,
`webx-cart__empty`; the button `webx-cart-add` (`is-busy`, `is-added`, `is-in-cart`), `webx-cart-add-form`, `webx-cart-add__error`.
Tokens: `--webx-cart-drawer-width` on `.webx-cart__dialog`, `--webx-cart-image-size` and `--webx-cart-gap` on `.webx-cart__panel`.

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
