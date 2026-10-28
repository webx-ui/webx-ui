# webx-ui/module-cart

A guest cart for WebX UI sites. Lines of anything a module sells go in through the `Purchasable`
contract and are priced live by their owner; totals are a chain other modules plug into; `cart()`
gives a template the counter and the full state.

```bash
composer require webx-ui/module-cart
php artisan migrate
```

Set the currency, unless the catalogue already has one:

```dotenv
WEBX_CART_CURRENCY=EUR
```

## What lies in a line

The cart stores a type, an id, the options and the quantity. Everything else — the name, the
price, the picture, whether it can be bought — the owner of the record answers when asked, a batch
of a type at a time:

```php
use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\PurchasableLine;

final class ProductPurchasable implements Purchasable
{
    public function resolve(array $refs, string $locale): array
    {
        return Product::query()->whereIn('id', $refs)->get()->mapWithKeys(fn (Product $product) => [
            $product->id => new PurchasableLine(
                name: $product->name,
                sku: $product->sku,
                price: $product->price_minor, // integer, minor units; 0 — price on request
                currency: 'EUR',
                url: $product->url,
            ),
        ])->all();
    }
}
```

The owner registers it from its own provider, only when the cart is installed:

```php
if (class_exists(\WebxUi\Cart\Purchasables::class)) {
    $this->callAfterResolving(\WebxUi\Cart\Purchasables::class, static fn ($cart) =>
        $cart->register('shop.product', ProductPurchasable::class));
}
```

A record that is gone (`null`) stays in the cart as «no longer available» with the name it had when
it was added; it counts for nothing and can be removed.

## Bundles

A batch is written whole or not at all. A line points at its parent by the parent's `key` in the
same batch; a child's quantity is per unit of the parent. Removing the parent keeps the children,
their quantity multiplied.

```php
app(\WebxUi\Cart\CartService::class)->add($cart, [
    ['type' => 'shop.product', 'ref' => 42, 'key' => 'set'],
    ['type' => 'shop.product', 'ref' => 57, 'qty' => 2, 'parent' => 'set'],
], 'en');
```

## Totals

`Calculator` counts lines → generated lines → subtotal → adjustments → total → tenders → payable,
with benefits and hints beside them. The five registries (`Totals\LineSources`,
`AdjustmentSources`, `TenderSources`, `BenefitSources`, `HintSources`) are empty here: discount
rules, coupons and wallets add their sources. Amounts are integers; every one has a
`*_formatted` string from `Money::format()`, written by the currency's rules, never the
language's: `"2 500,00 €"` for every visitor.

## In a template

```blade
<span data-webx-cart-count>{{ cart()->count() }}</span>
```

`count()`, `isEmpty()` and `has($type, $ref)` cost no query without the cookie and one with it.
`cart()->state()` is the full count.

A buy button — a form with `@csrf`, so it works without JavaScript:

```blade
<x-webx-cart::add type="shop.product" :ref="$product->id">Buy</x-webx-cart::add>
```

The widget — the icon with the counter, the main view where lines change (a drawer or a dialog)
and the mini cart under the icon, all drawn by the server:

```blade
<x-webx-cart />                    {{-- drawer, or WEBX_CART_WIDGET=dialog --}}
<x-webx-cart preview="none" />     {{-- no mini cart, or WEBX_CART_PREVIEW=none --}}
```

A buy button and `webx.cart.open()` always open the main view; «Edit cart» in the mini cart does
too. An item already at its maximum is not an error under the button: the cart opens with a
notice on that line.

Its script (`dist/cart.js`, committed) comes with the first widget or button on the page and
gives the page `window.webx.cart` — `add(items)` takes a bundle as one batch — and the
`webx:cart:change` event.

## The API

`GET /api/cart`, `POST /api/cart/items` (a batch), `PATCH` and `DELETE /api/cart/items/{id}`,
`DELETE /api/cart`; every answer is the whole state. The `web` group, CSRF and a throttle on
writes; a line of another cart is 404. The format and the guide: `apps/docs/guide/cart.md` in the
monorepo.

## Housekeeping

`php artisan webx:cart:prune` deletes carts untouched for `webx-cart.prune_after_days` (60); it is
on the schedule daily. `webx:doctor` says when the site has no currency.

Specification: `docs/architecture/WEBX_UI_MODULE_CART.md` in the monorepo.
