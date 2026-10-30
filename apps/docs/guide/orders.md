# Orders

`webx-ui/module-orders` is the order of a site on WebX UI: what the buyer bought, frozen at the
moment of the order, where it stands now, and what happened to it. It adds the «Orders» section
to the panel, a page of the order for the buyer, «My orders» in the visitor's account and the
`orders_*` tools for agents.

An order is made of a cart (`webx-ui/module-cart`) and lives on its own afterwards: changing a
price, a rule or a product never changes an order that was already placed. Delivery, payment,
visitors, notifications, the wallet and coupons are neighbours: each is optional and joins in
when it is installed. The shop's page that places an order is in the same package — see
[Checkout](/guide/checkout).

## Install

```bash
composer require webx-ui/module-orders
php artisan migrate
php artisan webx:demo --module=orders   # optional: twelve made-up orders
```

The migration seeds the default statuses in every language of the site. The cart's currency
(`WEBX_CART_CURRENCY`) is the orders' currency: an order keeps the one it was placed in.

The order number is `prefix + number`, counted from `WEBX_ORDERS_NUMBER_START` (1000) and padded
to `WEBX_ORDERS_NUMBER_PAD` digits. The prefix and the rest can be changed in the section's
settings, which show a trial number before saving.

## Statuses and milestones

An order has two kinds of state, and they answer different questions.

**Milestones** are facts: the order _was_ placed, paid, received, cancelled, refunded. The list is
closed (`WebxUi\Orders\Milestone`), each one is set once and never taken back — but `refunded`,
which a reversed refund record takes off (below) — and each has its event.

| Milestone          | Who sets it                                        | Event            |
| ------------------ | -------------------------------------------------- | ---------------- |
| `placed`           | `Orders::place()` only                             | `OrderPlaced`    |
| `awaiting_payment` | `place()` with `awaitPayment: true`                | part of `Placed` |
| `paid`             | the payment module, a manager, an agent, a status  | `OrderPaid`      |
| `received`         | the delivery module, a manager, an agent, a status | `OrderReceived`  |
| `cancelled`        | a manager, an agent, a status                      | `OrderCancelled` |
| `refunded`         | a refund, once everything is given back            | `OrderRefunded`  |

A repeated milestone is not an error but silence: `mark()` answers the first mark and fires
nothing, so a tracking update that comes after the manager's own mark doubles nothing. «Paid» or
«received» on a cancelled order is refused.

**Statuses** are the shop's way of working, and the shop names them. There are two levels:

- the **internal status** — what the manager works with: «New», «Taken into work», «Waiting for a
  call», «Being packed»…;
- the **public status** — the group it belongs to, the one the buyer sees: «Accepted», «In
  progress», «Shipped», «Completed», «Cancelled».

The order stores only its internal status; the public one is its group. Moving between internal
statuses is free, backwards too. A move inside one group tells the buyer nothing; entering
another group is what the buyer's notifications listen to.

A status may **set a milestone** (`paid`, `received` or `cancelled`): moving the order there marks
it with the source `status#<id>`. When the order may not take that milestone, the move is
refused whole, so «Paid» never stands on a cancelled order. A status and a milestone may disagree
on purpose: a gateway marked the order paid while it still stands in «Awaiting payment» until a
manager moves it — the list has a filter for exactly these.

Statuses are deleted softly. The screen says what would happen before it does: how many orders
stand there and where they go. The initial status, and the last active one of a group, cannot be
deleted.

## Everything goes through `Orders`

`WebxUi\Orders\Orders` is the one door to an order. The panel's controllers, the agent's tools,
the checkout and a neighbour's listener check the right, read the input, call **one** method and
return its answer. None of them saves the models of an order.

```php
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Orders;

$orders = app(Orders::class);

$orders->mark($order, Milestone::Paid, 'payment:gateway-a', reference: 'TX-42');
$orders->moveTo($order, $status, 'panel', note: 'Called the buyer');
$orders->note($order, 'The parcel came back to the depot.', 'delivery:carrier-a');
```

Why so strict:

- **Every method is one transaction with the order's row locked.** A gateway and a manager marking
  the order paid at the same moment make one `OrderPaid`, not two.
- **The history writes itself.** Each method writes its line — who, from where, when — so the
  card's timeline is complete whoever made the change.
- **The events fire after the commit.** A listener never sees an order that was rolled back.
- **A dry run answers the same.** `moveTo()`, `cancel()`, `refund()`, `reverseRefund()` and the visitor's link take
  `dryRun: true` and return what would happen — the milestone, the notifications, the refusal —
  without writing. The panel's dialogs and the agent's `dry_run` are this answer.

A refusal is an exception with the reason in words: `StatusMoveRefused`, `RefundRefused`, `RefundReversalRefused`,
`OrderCancelledAlready`, `MilestoneRefused`, `BlockRefused`.

### Cancelling and giving money back

- `cancel()` sets the milestone and gives back the order's tenders (wallet, certificates) — the
  event carries what came back, and the wallet returns exactly that. Money paid is **not** given
  back by a cancellation: `wasPaid` on the event tells the manager.
- `refund()` records money given back outside the site (a bank transfer, cash). It checks the
  amount, each tender and each line's qty against what is left of them. Once the money and every
  tender are back, the order gets `refunded`, once. The order's own lines and totals stay as
  they were; the refunds lie beside them.
- A refund recorded by mistake is never edited: `reverseRefund()` reverses it with a reason. The
  record stays in the history, struck through; what it gave back — money, tenders, units — comes
  off the order, and when the order is no longer given back whole `refunded` comes off too. The
  right one is then recorded as any refund; the panel offers it as soon as the record is reversed.
  `OrderRefundReversed` tells the wallet to take its tenders back.

### The balance: to pay more or to give back

An order keeps the money that came in for it, `paid_total` — the payment and any top-up after it —
beside what was given back. `$orders->balance($order)` answers the balance (`Balance`): what is
still `due`, what is `over` and is the buyer's to get back, and `kind()` in one word — `unpaid`
(nothing came in yet, the ordinary «to pay»), `topup` (paid, and owes more), `over`, or null when
settled. A refund never makes an order owe: it gives back money that came in, so a paid order with
one line given back stays settled. Only what came in against what the order costs now makes a debt
— an order whose lines were changed after it was paid, or one a gateway paid short.

- `mark(paid)` without an amount means «paid whole», as it always did; a gateway passes `amount`.
- `recordPayment($order, $amount, 'panel', reference: …, at: …, note: …)` records a top-up of a
  paid order — a line `payment` in the history and `OrderPaymentReceived`, never a second «paid».
  An order not paid yet is refused (its first payment is `mark(paid)`), a cancelled one too.
- A refund may give back up to `max(payable, paid_total) − refunded_total`: an overpayment is the
  buyer's even when it is more than the order costs now.
- A cancelled paid order shows what came in less what went back as money to give back — what the
  cancel dialog says.

The card's «Summary» shows «Paid» (the first payment and the top-ups apart, once there are any) and
the balance line «Top-up due» or «Give back»; a manager records a top-up with «Record a top-up»
(`orders.manage`, `POST {api}/orders/{id}/payments`, `dry_run` — the balance after it). The list
shows a badge under the total and filters «Payment differs» — waiting for a top-up or paid over —
by the stored `orders.balance` and its index; the figures over the list count the orders waiting
for a top-up. The revenue is what came in less what went back.

### Changing the lines of a placed order

The buyer calls back: one more of this, not that one at all. `$orders->editItems($order, new
ItemsEdit(revision: …, add: […], qty: [id => n], remove: [id], reason: …), dryRun: …)` changes the
lines of an order that is not received, cancelled or given back whole. The lines placed keep the
price the buyer ordered at and their discounts follow the quantity; a product added comes at
today's price, without the cart's rules; the discounts on the whole order, the delivery and the
benefits stay as placed. A set changes whole, a rule's gift may only go, a line keeps the units
returned of it. A line is never deleted: taken out, it keeps what was returned and is history.
Each edit is a row of `order_revisions` — every line before and after, the totals, the reason —
a line `items` of the history and the events `OrderItemsChanging` (in the transaction, a listener
may refuse) and `OrderItemsChanged` (after it).

In the card, «Change the lines» over «Items» (`orders.manage`) opens the editor: the lines with
their quantity and «Take out», a product found as on «New order», and the service's own dry run on
every change (`POST {api}/orders/{id}/items/preview`) — each line before → after, the totals, the
warnings. A paid order says what the edit does to its payment before the save («Save and recount
the payment»). The reason may be left out and only managers see it; «Message to the buyer» and
«Tell the buyer» go to the letter about the change. The quantities stand in one column on every
line; a quantity being typed is counted once the field is left — 0 then takes the line out, an
emptied field gets its number back. «Save» is greyed only with a reason it says beside it. The save (`PUT {api}/orders/{id}/items`) is sent
against `items_revision` the editor opened: an edit saved meanwhile — another tab — is refused,
and the editor reads the order again.

After the save the balance says what comes next: a paid order that owes more is offered «Record a
top-up» with the sum owed; one paid over — «Record a refund» with the money over filled in, the
lines and tenders left empty; tenders over the new total — a refund of them. The link to pay comes
with the payments module. «History» shows each edit — what was added, taken out and counted again,
the total before and after, the balance it left, the reason; «Summary» counts the edits, and the
list marks the order «Edited».

An agent does the same with `orders_items_edit` (`revision` from `orders_get`
`items_revision`, the reason optional), whose `dry_run` answers what the editor shows;
`orders_history` has every edit in full.

### Notes for whoever picks the order up next

Under the card's tabs is «Notes»: what one manager left for the next — «called, deliver after six».
A note is a line of the order's history (`kind = note`), so the feed holds a manager's note from
the panel, an agent's `orders_note` and a module's `$orders->note()` alike, each with who wrote
it and when, and the timeline shows the same lines. A line of the history never changes: a note is
not edited or deleted — a wrong one is followed by a right one.

Writing is `orders.manage` (`POST {api}/orders/{id}/notes`, up to 1000 characters); whoever only
reads the order sees the notes and no box. The node's public id is `order-notes`, for a neighbour
that wants something beside it. The feed is the panel's own [notes component](/guide/notes), the
one the inbox draws.

Over the feed stands the **pinned note** — the order's one standing line, «regular buyer, call only
in the morning», which a chronicle would bury. It is the order's `manager_note`: a tinted plate
captioned «Pinned», changed in place with the pencil and emptied to unpin; with none, a manager
sees only «Pin a note». It is saved as the contacts are (`PUT {api}/orders/{id}/details` with
`manager_note`, the same `Orders::updateDetails()`, the journal and the history's line
`details`), and `orders_update` still takes it. The tab «Buyer» no longer has the field.

### The list

Opened without a tab, «Orders» is on «New» while it holds any order and on «All» otherwise; a tab
in the address wins. The server decides by the counts it sends anyway (`view=auto` answers
`view`), so it is one request and no tab is shown before it says which.

Over the list stand the figures: orders, revenue (paid less given back), the average paid order
and what awaits payment now, for today, 7 or 30 days, with a bar a day for the last thirty under
them. The days are the site's calendar days in its timezone: «Today» starts at midnight, and 7 is
today and the six days before it. Money stays per currency. The count is `GET {api}/orders/summary` — the class
`Panel\OrdersSummary` that answers the agent's `orders_summary` too — asked apart, so the rows
never wait for it; folded or not, and the period, are remembered for the manager in the browser.

A row opens to what the order holds without going into it: the lines with their quantity and sum
and, under them, the totals as the card's «Summary» has them (what was paid or awaits payment,
what was given back, where the order came from), the buyer and the recipient, the buyer's comment, the pinned note first and the latest of the feed,
the lines of delivery and payment, the milestones with when. It is `GET {api}/orders/{id}/glance`
on opening, kept for the page — the list itself stays as light as it was. On a narrow list (cards)
a row does not open: the card is one tap from the order, and an opened card would push the next
one a screen away.

Phones and e-mails in the orders are links — `tel:` with the digits only, `mailto:` — in the
opened row, the buyer's cell, the narrow list's card and the card's visitor. Clicking one calls or
writes and does not open the order.

### An order taken by phone

A manager with `orders.manage` opens «New order» from the list: finds products by a word, picks a
variant where the product has one, sets the quantities and types the buyer's contacts. The totals
beside the form are the order's own count, made again on each change — prices, the cart's rules
and their warnings come from the site, never from the manager. «Create order» opens the card; the
card and the list mark it «Created in the panel», and the list filters it by `source = panel`.

Under it is `placeByManager()`: the lines go into a cart of its own, without a cookie, the order is
the same `place()` as the checkout's, and the cart is gone in the same transaction — so the order
gets its number, its letters and its events like any other. Code and the agent's `orders_create`
call it too:

```php
use WebxUi\Orders\Contact;
use WebxUi\Orders\ManagerOrder;

$order = $orders->placeByManager(new ManagerOrder(
    lines: [['type' => 'catalog.product', 'ref' => 42, 'qty' => 2]],
    locale: 'en',
    customer: new Contact('Alex', 'Doe', '+800 1234 5678', 'alex@example.com'),
));
```

Products are found through the cart: whichever module sells something implements
`FindsPurchasables` ([Cart](/guide/cart)), and «New order» offers it. Delivery and payment are not
chosen there; their modules fill the blocks later, as for a one-click order.

## Listening to an order

Every event is in `WebxUi\Orders\Events` and carries `OrderData` — a frozen snapshot of the order
at that moment, safe to queue — plus `source`, `adminId` and `at`. A listener that needs the model
takes it by `$event->order->id`.

| Event                      | When                                        |
| -------------------------- | ------------------------------------------- |
| `OrderPlacing`             | inside the transaction of `place()`         |
| `OrderPlaced`              | the order is made                           |
| `OrderPaid`                | milestone `paid`                            |
| `OrderPaymentFailed`       | a payment try did not go through            |
| `OrderReceived`            | milestone `received`                        |
| `OrderCancelled`           | milestone `cancelled`                       |
| `OrderRefunded`            | a refund is recorded; `full` on the last    |
| `OrderRefundReversed`      | a mistaken refund record is reversed        |
| `OrderStatusChanged`       | the internal status changed                 |
| `OrderPublicStatusChanged` | the public status changed                   |
| `OrderBlockUpdated`        | a block was written (below)                 |
| `OrderDetailsChanged`      | contacts, comment, note, the visitor's link |

A module listens the way it would to anything else, from its service provider, and only when the
orders are installed:

```php
use Illuminate\Support\Facades\Event;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderPaid;

if (class_exists(OrderPaid::class)) {
    Event::listen(OrderPaid::class, IssueCertificates::class);
    Event::listen(OrderCancelled::class, function (OrderCancelled $event): void {
        foreach ($event->tendersReturned as $tender) {
            // give back exactly $tender->amount
        }
    });
}
```

`OrderPlacing` is the one exception: it runs **inside** the transaction, with the model and the
cart's state. A listener that throws `OrderRefused` there — a wallet with too little in it, stock
that ran out — rolls the whole order back, and the cart stays as it was.

Cancellation and refund are separate events on purpose: a cancellation needs only the order, a
refund needs the amount and the lines. There is no «any milestone» event; listen to the ones you
need. On a move into a status with a milestone the order is `OrderStatusChanged`, then
`OrderPublicStatusChanged` if the group changed, then the milestone's event — all after one
commit.

## Blocks: `delivery` and `payment`

An order has two columns its neighbours fill: `delivery` and `payment`. The order does not know
delivery methods or gateways; the module that owns a block registers it and answers for its
shape.

```php
use WebxUi\Orders\Blocks\BlockDefinition;
use WebxUi\Orders\Blocks\OrderBlocks;

if (class_exists(OrderBlocks::class)) {
    $this->app->make(OrderBlocks::class)->register(new BlockDefinition(
        key: 'delivery',
        module: 'delivery',
        validate: fn (array $value): array => $value['method'] ?? null ? [] : ['A method is required.'],
        summary: fn (array $value, string $locale): string => $value['title'][$locale] ?? '',
    ));
}
```

The order reads three keys of a block itself — `method`, `title` (per language) and `price` — and
keeps the rest as it is, for the owner's tab of the card. `summary` is the one line the list,
the buyer's page and the mail show.

Later changes go through `patchBlock()`:

```php
$orders->patchBlock($order, 'delivery', ['waybill' => 'WB-1001'], 'delivery:carrier-a');
```

The patch is merged into the block (`array_replace_recursive`), checked by the owner's
`validate`, written with a history line, and `OrderBlockUpdated` carries the patch — the
notification «Your parcel number» listens to it. A block nobody registered, or one its owner
refuses, is `BlockRefused`.

The payment module that switches an order to a method that does not await payment calls
`stopAwaitingPayment()` after the patch: the order leaves «Awaiting payment» with a history line
and no event.

## The buyer

- **The order's page** — `/order/{number}` with a signed link, the one the mails carry
  (`{{public_url}}`). It shows the public status and its scale, the lines, the totals, the blocks'
  lines and the contacts; never the internal status or the manager's note. The page is
  `no-store` and `noindex`, and a wrong signature is `404`.
- **«My orders»** — with `webx-ui/module-users`, a section of the visitor's account with the
  orders in work counted on its badge. Another visitor's order is `404`.
- **Linking a guest's order.** In the card, «Link to a visitor» finds the account (the one with the
  order's e-mail first) and shows a dry run before linking. Only `user_id` changes: the contacts
  stay as the buyer typed them.

## Notifications

With `webx-ui/module-notifications`, the events above are notification events (`orders.placed`,
`orders.paid`, `orders.public_status`…). A public status keeps its own channels, the milestones'
channels are in the section's settings, and the site starts with three: «Order accepted» and
«Payment received» to the buyer, «New order» to the first e-mail of the site's contacts. The card
of an order shows what was sent about it.

**The letter never came.** A line of «Notifications» on the tab «History» — sent or failed —
goes again from its **Send again**: the same message to the same address, as a new line of the
log marked «Sent by hand» with who sent it. **Send the buyer a letter** in the `···` of the card
picks an event the order has a reason for — placed, the public status it stands in, paid,
cancelled… — shows the letter, and sends it to the order's e-mail as it is now, only through the
channels of that event that write to the buyer: the managers on the same channel are not written
to again. Each send is a line of the history with its author; the same send again within a minute
is refused, so a second press sends nothing. It is `orders.manage`, and from code
`Orders::notify($order, ['event' => 'orders.placed'], 'panel')` or `['delivery' => $id]`.

Without the package the orders work the same and send nothing; `php artisan webx:doctor` says so.

## For agents

The `orders_*` tools read and work orders the way the panel does: `orders_list`, `orders_get`,
`orders_history`, `orders_summary`, `orders_set_status` (one order or a batch by numbers),
`orders_mark`, `orders_payment_record` (a top-up), `orders_cancel`, `orders_refund`, `orders_refund_reverse`, `orders_notify` (a message again by hand),
`orders_update`, `orders_note`,
`orders_link_visitor`, the statuses and the settings. Every write takes `dry_run` and answers what
the panel's dialog would show. `orders://catalog` lists the statuses and what each one sets.

With the checkout on, `orders_checkout_*` look at the shop's page: the steps and why one is not
shown for a cart, a trial confirmation of a cart (`orders_checkout_preview` — what placing it
would answer, nothing written), the funnel of the last day, week and month, the drafts with their
contacts masked, and the tab «Checkout» of the settings. `orders://checkout` describes the page.

## Rights

| Right             | What it gives                                               |
| ----------------- | ----------------------------------------------------------- |
| `orders.view`     | the list, the card, the history                             |
| `orders.manage`   | statuses, milestones, cancellation, contacts, notes, blocks |
| `orders.refund`   | money given back — apart from the rest, because it is money |
| `orders.statuses` | the screens of the statuses and the section's settings      |

## Demo

`php artisan webx:demo --module=orders` makes twelve orders of made-up goods through the same
service: one in each public status, two awaiting payment (one after a failed try), one paid
while its status still waits, a cancellation unpaid and one paid and given back whole, a
received order with one line given back, a rule's discount with a gift line, a wallet's tender.
With visitors installed, three are the visitor's — one of them a guest's linked by hand. The demo
sends no notifications. A site that already has orders gets none; `webx:demo --remove` takes them
back.
