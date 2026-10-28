# Cart

`webx-ui/module-cart` is the cart of a site on WebX UI. Its lines live in the database; a guest
is known by a token in an httpOnly cookie, a signed-in visitor by the account. What a line holds and what it costs **now**
the cart asks the module that owns the record, through the `Purchasable` contract: the cart knows
no table of any other module, and the catalogue is just its first provider.

There is no cart page and no panel section. The cart gives a site a JSON API, `cart()` for
Blade, a buy button, a widget in three modes and reading tools for agents; a site that wants a
page of its own declares a route and a view on top of the API.

## Install

```bash
composer require webx-ui/module-cart
php artisan migrate
```

The cart sells in one currency: `WEBX_CART_CURRENCY=EUR`, or, left empty, the catalogue's
`WEBX_CATALOG_CURRENCY`. With neither, the cart refuses every write and `php artisan webx:doctor`
says which setting is missing.

With `webx-ui/module-catalog` installed and prices on, the product page's «Buy» button becomes
the cart's, and products go into the cart priced live. Nothing else to switch on.

## The API

Every route is in the `web` group (session, encrypted cookies, CSRF); writes are throttled to
`webx-cart.throttle` a minute per IP. The prefix is `webx-cart.path`, `api/cart` by default.

| Request                       | Body                                                          | What it does                                                                    |
| ----------------------------- | ------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| `GET /api/cart`               | —                                                             | The state. Without a cookie an empty one, and nothing is written.               |
| `POST /api/cart/items`        | `{ items: [ { type, ref, qty?, options?, key?, parent? } ] }` | Adds a batch: all lines or none. The first write makes the cart and its cookie. |
| `PATCH /api/cart/items/{id}`  | `{ qty?, options? }`                                          | Changes a line. `qty` is 1 or more; to take a line out, `DELETE` it.            |
| `DELETE /api/cart/items/{id}` | —                                                             | Takes a line out. A generated line's key (`rule%2312:0`) declines it.           |
| `DELETE /api/cart`            | —                                                             | The whole cart: its lines, the declined gifts and the cookie.                   |

- **Every answer is the whole state** (below), `200`; `POST` adds `added: [id, …]` — the lines
  it wrote or added to.
- **A batch is checked whole before anything is written.** One bad line — `422` for the batch,
  with the errors keyed by field (`items.1.ref`, `items.2.parent`), and nothing is written, not
  even the cart. Up to `webx-cart.batch_max` lines (20) a batch, `webx-cart.lines_max` (100) a
  cart, `webx-cart.qty_max` (999) a line.
- **A bundle** is a batch whose lines point at a parent by its `key` inside the same batch:
  `{ key: 'set', type, ref }, { parent: 'set', type, ref, qty: 2 }`. One level deep; a child's
  `qty` counts per unit of its parent. Taking the parent out leaves the children, each with its
  qty multiplied.
- **The same line twice** — same type, record and options, outside any bundle — adds to the qty
  of the one already there.
- **A line of another cart is `404`**, as one that never was.
- **The language** of names and messages is `X-Webx-Locale` when the site has it, else the
  request's.
- **Answers are private:** `Cache-Control: no-store`, `Vary: Cookie`.

A script sends the CSRF token as `X-XSRF-TOKEN` from the `XSRF-TOKEN` cookie, like the site's
forms. A request that does not ask for JSON is a form without JavaScript: it goes back to its page
with the outcome in the `webx-cart` flash (`added`), or with the errors as any form of the site.

```js
await fetch('/api/cart/items', {
  method: 'POST',
  credentials: 'same-origin',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
  },
  body: JSON.stringify({ items: [{ type: 'catalog.product', ref: 42, qty: 1 }] }),
})
```

## The state

Money is an integer in minor units everywhere — `4999` is 49.99 in a currency with two digits
(`exponent`). Beside every amount is a ready string, `*_formatted`, written by the currency's
rules and the same in every language of the site. Print the string; do not format the number.

```json
{
  "currency": "EUR",
  "exponent": 2,
  "locale": "en",
  "lines": [
    {
      "id": 101,
      "type": "catalog.product",
      "ref": 42,
      "source": null,
      "qty": 2,
      "effective_qty": 2,
      "name": "Item A",
      "sku": "A-100",
      "options": null,
      "options_label": null,
      "image": "/storage/catalog/42/a-100-320.webp",
      "url": "/item-a-42",
      "price": 4999,
      "price_formatted": "49,99 €",
      "price_at_add": 5999,
      "price_at_add_formatted": "59,99 €",
      "price_change": "down",
      "line_total": 9998,
      "line_total_formatted": "99,98 €",
      "shippable": true,
      "max_qty": null,
      "available": true,
      "gone": false,
      "declinable": false,
      "label": null,
      "adjustments": [],
      "children": []
    }
  ],
  "units": 2,
  "subtotal": 9998,
  "subtotal_formatted": "99,98 €",
  "adjustments": [],
  "total": 9998,
  "total_formatted": "99,98 €",
  "tenders": [],
  "payable": 9998,
  "payable_formatted": "99,98 €",
  "benefits": [],
  "hints": [],
  "warnings": [{ "code": "price.down", "line": 101, "message": "Item A is now cheaper." }]
}
```

- **`lines`** is a tree: a bundle's children are under it in `children`.
- **The price is live** — the provider's now. `price_at_add` is what it was when the line went
  in, for «it got cheaper» and for analytics.
- **`gone: true`** — the record is deleted: the name is the snapshot taken when it went in, the
  price is `null` and the line counts for nothing; it can be removed. **`available: false`** — the
  record is there but cannot be bought now (off the site, price on request): it counts for
  nothing either.
- **`units`** is what the header counter shows: the lines' units, unavailable ones included.
- **The chain** — `subtotal` → `adjustments` → `total` → `tenders` → `payable`, with `benefits` and
  `hints` beside it — is empty in the cart itself; discounts, gifts and balances come from
  satellite modules. A generated line (a gift) has a string id, `"rule#12:0"`, and a `source`.
- **`warnings`**: `line.gone`, `line.unavailable`, `line.currency`, `line.qty_limited`,
  `price.down`, `price.up`, `tenders.capped`, `totals.source_failed` — each with a ready
  `message` in the request's language.

## In Blade

```blade
{{-- The counter in the header: no query without a cookie, one with it. --}}
<span data-webx-cart-count>{{ cart()->count() }}</span>

{{-- A buy button: a form with @csrf, so it works without JavaScript too. --}}
<x-webx-cart::add type="catalog.product" :ref="$product->id" />
<x-webx-cart::add type="catalog.product" :ref="$product->id" :qty="2" class="button">Buy</x-webx-cart::add>
```

The button carries `is-in-cart` when the record is in the cart already, and the
`data-webx-cart-add="<type>:<ref>"` and `data-webx-cart-qty` attributes the widget's script reads
to send it in place. `cart()->state()` is the whole state above, counted once a request.

In a view that has to compile on a site without the cart, name the component dynamically —
`<x-dynamic-component component="webx-cart::add" … />` — as the catalogue's product page does:
Blade looks a component tag up when it compiles the view.

## Signed-in visitors

With a visitors module (`webx-ui/module-users`, [Site visitors](./site-visitors.md)) the cart
belongs to the account as well. The cart does not require that module: it listens to the events
of the panel's seam (`WebxUi\Admin\Visitors\Events\*`), and on a site without one every visitor
is a guest and nothing changes.

- **A signed-in visitor's cart is found by the account** (`carts.user_id`), on every device — the
  cookie is not needed. A guest's is found by the cookie, and the cookie opens only a guest's cart:
  whoever still holds the cookie of an account's cart opens nothing.
- **At sign-in the guest's cart joins the account's.** Its lines are added to the account's cart:
  a line like one already there (the same record and options, outside a bundle) adds its qty, up
  to `webx-cart.qty_max` and the provider's `maxQty`; a bundle comes over whole and never merges.
  The declined gifts of both carts stay declined. The guest's cart is deleted and the cookie gets
  a new token for the account's. Without an account cart yet, the guest's becomes it.
- **At sign-out** the cookie goes: the guest starts with an empty cart, the account's waits for
  the next sign-in.
- **Deleting the account** deletes its cart.

Agents see whose a cart is: `cart_list` and `cart_get` give `user_id` (null — a guest's).

## The widget

```blade
<x-webx-cart />                    {{-- drawer and the mini cart: webx-cart.widget.mode, .preview --}}
<x-webx-cart mode="dialog" />
<x-webx-cart preview="none" />     {{-- the icon opens the main view at once --}}
```

The widget has a **main view**, where the lines change, and an optional **mini cart** under the
icon for a glance.

| Prop      | Value                | What it is                                                                      |
| --------- | -------------------- | ------------------------------------------------------------------------------- |
| `mode`    | `drawer` (default)   | the main view at the edge of the window: lines with steppers, totals, checkout  |
| `mode`    | `dialog`             | the same in a dialog in the middle                                              |
| `preview` | `dropdown` (default) | the mini cart: the lines, the total, «Edit cart» to the main view, the checkout |
| `preview` | `none`               | no mini cart: the icon opens the main view                                      |

Settings: `WEBX_CART_WIDGET=drawer|dialog`, `WEBX_CART_PREVIEW=dropdown|none`; any other value of
the prop or the setting throws. **A buy button and `webx.cart.open()` always open the main view**,
never the mini cart. The icon opens the mini cart on a wide header and the main view on a header
under 640 px or in the mobile menu: on a phone the dropdown is not shown.

On a phone the drawer and the dialog are a sheet from the bottom. The drawer is the widgets'
`<x-webx-dialog drawer>` — the same modal dialog (focus kept inside, Esc, the backdrop, focus given
back to the trigger) pressed to the edge; any module can use it.

`theme-default` puts `<x-webx-cart />` among the header's actions when the cart is installed. In a
header of your own, put it where the icon belongs; the mode and the mini cart are props or settings.

**What the page prints** is the trigger, the counter from `cart()` and empty panels. Their lines
and totals are drawn by the server when one opens: the script asks `GET /api/cart` with
`X-Webx-Cart-View: <mode>` and `X-Webx-Cart-Preview: dropdown`, and the answer carries the main
view's HTML as `html` and the mini cart's as `preview_html` beside the state.
Every sum in it is the server's `*_formatted` string; the script formats nothing. The checkout
button leads to `WEBX_CART_CHECKOUT_URL`, or to the site's route named `checkout`; with neither
there is none.

**Without JavaScript** the buy button is a form: it puts the line in and comes back to the page,
which then prints the main view open with its lines. Otherwise the panel says «Turn on JavaScript to
see your cart».

**The script** comes with the first widget or buy button on the page (`dist/cart.js`, under 6 KB
gzip). Any element with `data-webx-cart-add` is a buy button:

| Attribute                        | What it does                                       |
| -------------------------------- | -------------------------------------------------- |
| `data-webx-cart-add="type:ref"`  | one line                                           |
| `data-webx-cart-qty="N"`         | how many (1)                                       |
| `data-webx-cart-qty-from="#qty"` | the quantity from a field on the page              |
| `data-webx-cart-options='{…}'`   | the line's options, for a provider that takes them |
| `data-webx-cart-open="false"`    | do not open the main view after adding             |

While it sends, the button has `is-busy`; then `is-added` for two seconds and «Added to cart» is
said to a screen reader.

**A refusal.** When the item is already in the cart at its maximum (`webx-cart.qty_max`, or the
provider's `maxQty`), the button still opens the main view: nothing changes, and the line says
«You already have the maximum quantity of this item.» as a quiet notice, with the stepper right
there to take it down. Any other refusal (cannot be bought, no price, another currency) into a
cart that exists opens the main view too, the reason above the totals. The API answers both with
422, the errors **and** the state with its panels. Only with no cart yet — nothing to open — is the
refusal said in the alert under the button. Without JavaScript the form at the maximum comes back
with the cart open and the same notice; other refusals come back as form errors. And for the rest of the page:

```js
// A bundle: one batch, the child pointing at its parent's key; all or nothing.
await webx.cart.add([
  { type: 'catalog.product', ref: 42, key: 'set' },
  { type: 'catalog.product', ref: 57, parent: 'set', qty: 2 },
])

webx.cart.setQty(101, 3)
webx.cart.remove(101)
webx.cart.open()
const state = await webx.cart.state()

document.addEventListener('webx:cart:change', (event) => {
  console.log(event.detail.state.units)
})
```

**The look** is `resources/theme/cart.css` of the module on site tokens — the classes are listed
under `### Classes` in its `AGENTS.md`. A theme restyles them with the same class, overrides the
views in `views/vendor/webx-cart/` (`widget`, `widget/line`, `widget/totals`, `widget/empty`,
`icons/cart`) and adds to the panel at the points `parts/cart/line/after`,
`parts/cart/totals/before`, `parts/cart/totals/after` and `parts/cart/footer`.

## Your own provider

Anything a module sells goes into the cart the same way the catalogue's product does. Implement
`WebxUi\Cart\Contracts\Purchasable` — a batch of ids of one type in, a line or `null` for each out:

```php
use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\Money;
use WebxUi\Cart\PurchasableLine;

final class TicketPurchasable implements Purchasable
{
    public function __construct(private readonly Money $money) {}

    public function resolve(array $refs, string $locale): array
    {
        $lines = [];

        // One query for the batch, never one a line.
        foreach (Ticket::query()->whereKey($refs)->get() as $ticket) {
            $lines[$ticket->id] = new PurchasableLine(
                name: $ticket->getTranslation('title', $locale),
                sku: $ticket->code,
                price: $this->money->toMinor($ticket->price, 'EUR'),
                currency: 'EUR',
                url: $ticket->url($locale),
                shippable: false,
                maxQty: $ticket->seats_left,
                available: $ticket->is_on_sale,
                names: $ticket->getTranslations('title'),
            );
        }

        return $lines; // a ref left out is gone
    }
}
```

- **Build `PurchasableLine` with named arguments.** New fields only ever come last and optional.
- `price` is an integer in minor units; `0` means «price on request» and cannot be bought. Read a
  decimal with `Money::toMinor()`, never through a float.
- `maxQty` and `available` are optional: without stock, leave `maxQty` null and nothing is
  checked.
- `names` — the name in every language of the site — is kept with the line, so a deleted record
  still shows its name.
- A price that depends on a choice: implement `ResolvesWithOptions`; accepting options at all:
  `ChecksOptions`. Without it, a line with options is refused.

Register it from your provider's `boot()`, so your module works with the cart and without it:

```php
use WebxUi\Cart\Purchasables;

if (class_exists(Purchasables::class)) {
    $this->callAfterResolving(Purchasables::class, static function (Purchasables $cart): void {
        $cart->register('events.ticket', TicketPurchasable::class);
    });
}
```

The type is `<module>.<entity>`. The registry is made only by the cart's provider, so without the
cart the callback never runs; put `webx-ui/module-cart` in your package's `suggest`. Keep a class
that implements the contract out of code that runs without the cart — loading it there fails.

## Agents

With the panel and `webx-ui/mcp` installed, an agent can read the carts — and only read them: a
cart is the visitor's, and nothing but the visitor changes it. The tools are behind the scope
`cart:read` and the permission `cart.view`, which a role gives (there is no menu section).

| Tool           | What it answers                                                                                                                                                                                                 |
| -------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `cart_list`    | carts, latest changed first, twenty a page: id, `user_id`, language, currency, lines, units, the sum at the prices of adding (not recounted), dates; `updated_since`, `updated_before`, `contains`, `min_units` |
| `cart_get`     | one cart counted the way the visitor sees it now — the API's answer, plus the dates, the declined rules and each adjustment's and tender's `meta`; `locale` for another language                                |
| `cart_summary` | carts in all, changed in the last 1, 7 and 30 days, abandoned (lines in them, untouched for `days`), and the records in most carts with their names                                                             |
| `cart_quote`   | the lines of a `POST` put through every check and counted, then nothing kept: the state, or the refusals field by field; `declined` counts without those rules' gifts                                           |

The resource `cart://catalog` lists the purchasable types, the sources of every link of the
chain with their priorities, the currency and the limits, the fields of a state and of a line,
and the warning codes with their phrases — read from the registries, so a satellite shows up
there once it is installed.

No answer carries the token, an IP or a cookie: a cart is named by its id. When a discount did
not apply, start from `cart_quote` with the same lines.

## Read more

- `php/packages/module-cart/AGENTS.md` — the contract, the registries and what not to do.
- Specification: [`WEBX_UI_MODULE_CART.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_CART.md).
