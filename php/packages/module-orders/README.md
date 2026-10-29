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

## Cancelling and refunds

```php
$orders->cancel($order, 'panel', 'The buyer asked');   // the tenders left go back to the wallet

$orders->refund($order, new RefundRequest(
    amount: 4999,                     // money given back outside the site, minor units
    tenders: [$tenderId => 400],      // back to the wallet
    items: [$itemId => 1],            // units returned
    reference: 'BANK-1',
));
```

A cancellation does not give money back: its answer says whether the order was paid and how much
is left. A refund only records what the manager gave back — a bank, a till, the ledger — and each
part is kept to what is left. When the money and every tender are back, the order gets the
milestone «refunded» and `OrderRefunded` comes with `full = true`. Both take `dryRun: true`.

## In the panel

«Orders» lists what was placed, with four views — new, awaiting payment, payment failed, all — a
search by number, phone in any spelling, e-mail or name, and filters by both statuses, the
milestones, the period and the methods of delivery and payment. The selection moves to a status or
cancels, each with a preview of what it does first. The card of an order is the screen
`orders.show`: the status with what the buyer sees, the lines, the buyer's contacts and the
notes, the history, the totals. A module adds its own tab to it with a patch:

```php
Screens::extend('orders.show', [
    ['op' => 'add', 'target' => 'tabs', 'position' => 'after:items', 'node' => [
        'id' => 'delivery', 'type' => 'wx-tab', 'label' => 'Delivery', 'children' => [/* … */],
    ]],
]);
```

Every action of the panel is a call of `Orders`: nothing there writes an order of its own.

«Orders → Statuses» holds both levels: what the buyer sees, and the stages of the shop inside
each, with the orders standing in every one. Both are dragged; a stage dragged into another group
is bound to it, and the panel first says how many buyers will see another name — at once, without
a message. A stage is deleted softly: its orders stay in it, and «Deleted» brings it back. What
would leave an order nowhere to go is refused: the status new orders come into, the last active
stage of a group with orders, a public status with stages still in it. In code the same is
`OrderStatuses`.

«Orders → Settings» is the format of the number, with the number the next order would get, and
the statuses a new order comes into. They are kept with the site's settings (`module-settings`);
without it, the config `webx-orders` decides.

A status that cancels the order is a cancellation: moved there from the panel or by an agent, the
order needs a reason, given as the note.

## For an agent

The same through the panel's MCP server: `orders_list`, `orders_get`, `orders_history` and
`orders_summary` read; `orders_set_status` (one order, or up to 200 by `numbers`),
`orders_mark`, `orders_cancel`, `orders_refund`, `orders_update` and `orders_note` work an
order; `orders_statuses_*`, `orders_public_statuses_*` and `orders_settings_get|set` are the two
screens. Each is a call of `Orders` or `OrderStatuses` with the source `mcp`, behind the
permission the panel asks for, and `dry_run: true` answers what the panel's dialog shows — the
batch included — without changing anything. `orders://catalog` lists the milestones, the palette,
both levels of the statuses, the blocks and the settings. `php artisan webx:mcp-tools
--module=orders` shows them all.

## Status

This release has the tables, the placing of an order, its milestones and statuses, cancelling and
refunds, the list and the card in the panel, the screens of the statuses and the settings, the MCP
tools, the buyer's page, and the checkout page with the steps «Contacts» and «Comment» placing an
order with plain forms: a repeat of the confirmation is the same order, an amount that went down
is placed at once, the agreement with the terms is a line of the order's history, a honeypot, a
mark of time and limits guard it, and «Thank you» follows. Delivery and payment in the checkout
and its look come in the next ones; the spec is
[`WEBX_UI_MODULE_ORDERS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_ORDERS.md).

## License

MIT
