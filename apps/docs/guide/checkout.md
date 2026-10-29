# Checkout

The checkout is the shop's own page that turns a cart into an order: `/checkout`. It is part of
`webx-ui/module-orders` — there is no separate package — and it is on as soon as the orders are
installed. An order placed there is the same order the panel, an agent or the site's code make:
it goes through `Orders::place()` (see [Orders](/guide/orders)).

```
┌──────────────────────────────────────────┬──────────────────────┐
│ 1. Contacts      Buyer A, +800 1234 5678 │ Your order           │
│ 2. Delivery      (a slot, if installed)  │ 3 items for  …       │
│ 3. Payment       (a slot, if installed)  │ Discount     …       │
│ 4. Comment                               │ Delivery cost …      │
│                                          │ ──────────────       │
│                                          │ To pay       …       │
│                                          │ [ Place the order ]  │
└──────────────────────────────────────────┴──────────────────────┘
```

The page is a column of **steps** and the **column of the totals** with the button. In a narrow
container the column goes under the steps and a sticky line «To pay · Place the order» stays at
the bottom. Without JavaScript the page still works: every step is a form of its own.

## What the buyer goes through

| Address                      | What                                                           |
| ---------------------------- | -------------------------------------------------------------- |
| `GET /checkout`              | the page; an empty cart — «Your cart is empty» and a link back |
| `POST /checkout/steps/{key}` | saves one step into the draft                                  |
| `GET /checkout/summary`      | the column of the totals, for the script to redraw             |
| `POST /checkout/place`       | the confirmation                                               |
| `GET /checkout/thanks/{no}`  | «Thank you», only for the session that placed the order        |

All of them sit inside the site's `{locale?}`; the prefix is `webx-orders.checkout.path`. The
cart's button leads to the route named `checkout` by itself.

- **Only the visitor's own cart.** The checkout never takes a cart's id: a guest's cart is the one
  of the cookie, a signed-in visitor's is the account's.
- **The draft** (`order_checkout_drafts`, one per cart) keeps what the buyer typed. Showing the
  page makes none; the first saved step does. After the order it keeps the contacts alone, so the
  same guest's next order starts filled in. `webx:orders:checkout-prune` takes old drafts away
  (`draft_days`, 30 by default) and runs daily.
- **Signing in** from the contacts step joins the guest's cart and draft to the account's, and the
  page redraws without a reload.
- **The amount is checked.** The column carries the amount the buyer saw. If the cart counts more
  at the confirmation — a price went up, a discount ended — the buyer gets the new column and
  confirms again; if it counts less, the order is placed at once and «Thank you» says by how
  much. A double click, two tabs or a repeated request make one order.
- **Antispam** is a honeypot, a time mark (`min_seconds`), the `Origin` and the limits of
  `throttle`.

The shop sets the rest in the panel, section «Orders» → settings → tab «Checkout» (right
`orders.checkout.settings`): which of last name, phone and e-mail are asked and required, the
«Recipient is another person» box, the comment step and the agreement with the terms — none, a
notice under the button, or a checkbox. The agreement is written into the order's history with
the page and its version.

## Steps

A step is a section of the page. The registry `CheckoutSteps` keeps them by position:

| Key        | Position | Whose                              | What                                      |
| ---------- | -------- | ---------------------------------- | ----------------------------------------- |
| `contacts` | 100      | the checkout                       | the buyer, signing in, the recipient      |
| `delivery` | 200      | a `ShippingSlot` (delivery module) | shown when the cart has a line to ship    |
| `payment`  | 300      | a `PaymentSlot` (payment module)   | after delivery: a method may depend on it |
| `comment`  | 900      | the checkout                       | off with the setting «Comment»            |
| `confirm`  | —        | the checkout                       | the column of the totals; never removed   |

Every step prints its form and a one-line summary. The section is `is-done` when the summary is
there and nothing is missing, `is-open` for the first one left to fill. Whether a done step folds
into its summary is the theme's choice — the attribute `data-steps` of the page's root: `open` in
the package, `collapse` in `theme-default`.

The button is never disabled. Pressing it checks every step (`check()` with `Purpose::Order`),
answers the errors under their fields and leads the focus to the first one.

### A step of your own

A project adds a step between the others by position: a class of
`WebxUi\Orders\Checkout\Steps\Step`. The order has no free fields, so a step keeps its value in the
draft's `steps` and writes it into the order's history while the order is being placed — in a
listener of `OrderPlacing`, inside the order's transaction.

```php
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;
use WebxUi\Orders\Checkout\Steps\Step;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Orders;

final class CallTimeStep implements Step
{
    public const string KEY = 'call-time';

    private const array TIMES = ['morning', 'afternoon', 'evening'];

    /** What contribute() took from the draft, for the order being placed now. */
    private ?string $placing = null;

    public function __construct(private readonly CheckoutLinks $links) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return __('When to call');
    }

    public function applies(CheckoutContext $context): bool
    {
        return true;
    }

    public function render(CheckoutContext $context): Htmlable
    {
        return view('checkout.call-time', [
            'action' => $this->links->step(self::KEY, $context->locale),
            'value' => $this->value($context),
            'times' => self::TIMES,
        ]);
    }

    public function summary(CheckoutContext $context): ?string
    {
        return $this->value($context);
    }

    public function save(CheckoutContext $context, array $input, CheckoutDraft $draft): void
    {
        $time = $input['call_time'] ?? null;

        if (! in_array($time, self::TIMES, true)) {
            throw ValidationException::withMessages(['call_time' => __('Choose a time.')]);
        }

        $draft->steps = [...($draft->steps ?? []), self::KEY => $time];
    }

    public function check(CheckoutContext $context, Purpose $purpose): array
    {
        return []; // optional: an order without it is fine
    }

    public function contribute(CheckoutContext $context, PlaceOrderBuilder $order): void
    {
        $this->placing = $this->value($context);
    }

    /** Inside the order's transaction: the line is written with the order or not at all. */
    public function placing(OrderPlacing $event): void
    {
        if ($this->placing !== null) {
            app(Orders::class)->note($event->order, "Call in the {$this->placing}.", 'checkout:call-time');
            $this->placing = null;
        }
    }

    private function value(CheckoutContext $context): ?string
    {
        return $context->draft?->steps[self::KEY] ?? null;
    }
}
```

The view is a form of `widgets` posting to the step's address, like the package's own steps:

```blade
<x-webx-form :action="$action" id="checkout-step-call-time-form">
    <x-webx-field type="radio" name="call_time" :label="__('When to call')" :value="$value"
        :choices="array_combine($times, $times)" />
    <x-webx-form.submit>{{ __('Save') }}</x-webx-form.submit>
</x-webx-form>
```

Register it in a service provider — one instance for the registry and the listener:

```php
use Illuminate\Support\Facades\Event;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Events\OrderPlacing;

public function register(): void
{
    $this->app->singleton(CallTimeStep::class);
}

public function boot(): void
{
    $this->callAfterResolving(CheckoutSteps::class, function (CheckoutSteps $steps): void {
        $steps->register($this->app->make(CallTimeStep::class), 250);
    });
    Event::listen(OrderPlacing::class, [CallTimeStep::class, 'placing']);
}
```

- **The key** — lowercase letters, digits and dashes; it is the step's address
  (`/checkout/steps/call-time`) and its name for agents. `confirm` is taken.
- **A step with a key that exists replaces it**: registering your own `contacts` changes the
  contacts step; `$steps->remove('comment')` takes one away.
- **`check()` refuses with `StepError`** — the field, the message and a `code` for agents
  (`call-time.required`). A step whose `check()` is not empty keeps the order from being placed.
- **`contribute()` sets only what the step owns** on the builder: the buyer, the recipient, the
  comment, a block. Two steps putting the same block into one order is an exception.
- **Read the cookie and the session only through `CheckoutContext`**: the cart, its state counted
  once a request, the draft, the visitor, the language.

## Slots: delivery and payment

Delivery and payment are not the checkout's: they are **slots** their modules fill. The checkout
knows two keys, `delivery` and `payment`, and the contracts in `WebxUi\Orders\Checkout\Slots`:

- `Slot` — `needed()`, `render()`, `summary()`, `check()`, `snapshot()` (the order's block);
- `ShippingSlot` — plus `totalsLine()` (the line «Delivery cost»: an amount, «Free» with the old
  price crossed out, «at carrier rates») and `choice()` — what the payment step may know about the
  chosen delivery;
- `PaymentSlot` — plus `awaitsPayment()`, `next()` (the gateway's page right after the order) and
  `payAgainUrl()` («Pay now» on «Thank you» and the order's page).

A module registers its slot only when the orders are installed:

```php
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;

if (class_exists(CheckoutSlots::class)) {
    $this->callAfterResolving(CheckoutSlots::class, fn (CheckoutSlots $slots) => $slots->register(new DeliveryCheckoutSlot(...)));
}
```

The checkout wraps the slot into a step at its position. The slot keeps the buyer's choice by the
cart in its own tables, prints its own components and talks to its own API; after every change it
dispatches `webx:checkout:slot-changed` on `document`, and the page redraws the column of the
totals. A slot that throws while the page is shown leaves a section «Could not load» and a log
line; one that throws at the confirmation answers an error, and no order is made.

Without either module the page has two steps — contacts and comment — and the order comes in
without the blocks `delivery` and `payment`: that is a shop that calls back about both. A cart
without a line to ship has no delivery step even with the module installed.

In the monorepo, a slot's module runs its slot through `SlotContractTestCase` of
`module-orders/tests/Checkout`; `FakeShippingSlot` and `FakePaymentSlot` of `tests/Fixtures` show the
shape.

## A part of the column

`CheckoutSummaryParts` is the place for a neighbour's piece of the column of the totals, between
the lines and «To pay» — the promo code's field, a sum from a wallet. It is empty in the package.

```php
use Illuminate\Contracts\Support\Htmlable;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Summary\CheckoutSummaryParts;
use WebxUi\Orders\Checkout\Summary\SummaryPart;

final class PromoCodePart implements SummaryPart
{
    public function applies(CheckoutContext $context): bool
    {
        return ! $context->isEmpty();
    }

    public function render(CheckoutContext $context): Htmlable
    {
        return view('coupons::checkout-field', ['state' => $context->state]);
    }
}

$this->callAfterResolving(CheckoutSummaryParts::class, function (CheckoutSummaryParts $parts): void {
    $parts->register('promo-code', new PromoCodePart, 100);
});
```

A part changes the cart only through its own API — a code becomes a discount of the cart's chain
— and then dispatches `webx:checkout:slot-changed` with its key as `slot`. The column is drawn
again from the cart, so the amount the confirmation checks is always the one on the screen. A
part that throws is left out and logged; the column and its button stay.

## The look

The page is the composition `checkout.page` of the module, inside the layout of the handle
`checkout.page` — `layout.minimal` of `theme-default` by default (a light header, no footer).
`webx-orders.checkout.layout = 'layout'` gives it the site's full layout. A theme moves the nodes by
a patch, never by a copy of the view:

| Node                     | What                                                       |
| ------------------------ | ---------------------------------------------------------- |
| `checkout`               | the root; `data-steps` — `open` or `collapse`              |
| `checkout.main`          | the column of the steps                                    |
| `checkout.steps`         | the steps — protected: moved, never removed                |
| `checkout.aside`         | the column of the totals                                   |
| `checkout.summary`       | the totals and the button — protected                      |
| `checkout.summary.parts` | the parts above                                            |
| `checkout.trust`         | an empty slot for «Free returns» and the like              |
| `checkout.sticky`        | the sticky line of a narrow container; `remove` to take it |

«Thank you» has the handle `checkout.thanks`. The page dispatches `webx:checkout:placed` there —
the place for a counter's goal.

## Turning it off

A site that takes orders some other way — its own page, a phone call, an agent — turns the page
off:

```dotenv
WEBX_ORDERS_CHECKOUT=false
```

Then there are no checkout routes (nor the route named `checkout`, so the cart's button leads to
`WEBX_CART_CHECKOUT_URL` or disappears), no drafts kept, no prune command, no tab «Checkout» in
the settings and no `orders_checkout_*` tools. The orders, the panel, the buyer's page and the
notifications work as before, and an order comes from the site's code:

```php
use WebxUi\Orders\Contact;
use WebxUi\Orders\Orders;
use WebxUi\Orders\PlaceOrder;

$order = app(Orders::class)->place(new PlaceOrder(
    cart: cart()->cart(),
    locale: app()->getLocale(),
    customer: new Contact('Buyer', phone: '+800 1234 5678', email: 'buyer@example.com'),
    expectedPayable: $shownPayable, // the amount the buyer saw; null — no check
    idempotencyKey: $formToken,     // the same key — the same order
    userId: $visitorId,            // null — a guest's order
    source: 'site',
));
```

`place()` empties the cart and keeps it, refuses a cart with lines that cannot be bought
(`CartNotOrderable`) and an amount that went apart from the one shown (`PayableChanged`).

## For agents

With the checkout on, `orders_checkout_steps` lists the steps and why one is not shown for a cart,
`orders_checkout_preview` answers what placing a cart would answer without writing anything,
`orders_checkout_funnel` counts the drafts by their last step for a day, a week and a month,
`orders_checkout_drafts_list` shows the drafts with the contacts masked, and
`orders_checkout_settings_get` / `_set` read and change the tab «Checkout» (`dry_run` says what
would change on the page). `orders://checkout` describes the steps, the slots, the parts, the
settings and the codes of the answers.
