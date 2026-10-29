# webx-ui/module-orders

Orders for WebX UI sites, and the checkout that places them. An order is a snapshot of the cart
at the moment it is placed — lines, prices, discounts, tenders, benefits, the buyer's contacts and
the blocks that delivery and payment write — and depends on no live record afterwards.

```bash
composer require webx-ui/module-orders
php artisan migrate
```

It requires `webx-ui/module-cart`, `webx-ui/widgets` and `webx-ui/compositions`. Delivery,
payment, visitors and notifications are optional neighbours: an order without them is lines,
contacts and a comment.

## Two axes

**Milestones** are fixed facts the system and the neighbours rely on — `WebxUi\Orders\Milestone`:
placed, awaiting payment, paid, received, cancelled, refunded. Each is set once and never taken
back.

**Statuses** are the shop's own stages, in two levels: internal ones the staff work in («Being
packed»), each inside one public group the buyer sees («In progress»). A site starts with a default
set, seeded by the migration in every language of the site from the `statuses` group of the
`webx-orders` dictionary; from then on the rows are the site's to rename, recolour and reorder. An
internal status can set a milestone when an order is moved into it — the default «Completed» sets
`received`, «Cancelled» sets `cancelled`.

The colour of a status is a name from the closed palette `WebxUi\Orders\StatusColor` — `neutral`,
`info`, `success`, `warning`, `danger`, `accent` — which the panel turns into its theme tokens.

## Tables

`orders`, `order_items`, `order_adjustments`, `order_tenders`, `order_benefits`,
`order_milestones`, `order_refunds`, `order_status_changes`, `order_public_statuses`,
`order_statuses`. Money is an integer in the minor units of the order's currency everywhere.

## Placing an order

Every change of an order goes through the service `WebxUi\Orders\Orders`. An order comes to be
only by `place()`:

```php
use WebxUi\Orders\{Contact, Orders, PlaceOrder};

$order = app(Orders::class)->place(new PlaceOrder(
    cart: $cart,
    locale: 'en',
    customer: new Contact('Alex', 'Doe', phone: '+1 555 010-0000', email: 'alex@example.test'),
    expectedPayable: 29298,      // what the buyer saw; null — no check
    idempotencyKey: $formToken,  // a repeat returns the same order
));
```

It counts the cart again under the lock of its row, refuses a cart that cannot be ordered
(`CartNotOrderable`) or an amount the buyer did not see (`PayableChanged`), writes the snapshot,
lets `OrderPlacing` listeners do what must happen with it (or refuse with `OrderRefused`), empties
the cart — its row and cookie stay — and dispatches `OrderPlaced` after the commit. The number is
made of the id: `webx-orders.number` (`prefix`, `start`, `pad`).

## Milestones and statuses

After that the order changes only through the same service, each call one transaction with the
order's row locked, its events after the commit:

```php
$orders->mark($order, Milestone::Paid, 'payment:gateway-a', reference: 'PAY-1'); // once; a repeat is silence
$orders->moveTo($order, $packed, 'panel', dryRun: true);  // StatusMove: what would happen
$orders->moveTo($order, $completed, 'panel');            // and the milestone «received» it sets
$orders->patchBlock($order, 'delivery', ['waybill' => 'WB-100'], 'delivery:carrier-a');
$orders->updateDetails($order, (new OrderDetails)->managerNote('Call after six'), 'panel');
```

`paymentFailed()` records a failed try, `stopAwaitingPayment()` takes the «awaiting payment» flag
down when the method was switched to one that does not await it, `note()` writes a line of the
history. Lines, money and milestones are never edited.

## Status

This release has the tables, the placing of an order, its milestones and statuses. Cancelling
with a reason, refunds, the screens of the panel, the MCP tools, the buyer's page and the checkout come in the
next ones; the spec is
[`WEBX_UI_MODULE_ORDERS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_ORDERS.md).

## License

MIT
