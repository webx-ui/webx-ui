# webx-ui/module-orders

Orders and the checkout that places them. An order is a snapshot of the
cart (`webx-ui/module-cart`) at placing: lines, prices, discounts, tenders, benefits, the contacts
of the buyer and the recipient, and the blocks delivery and payment write. After that it depends
on no live record. It moves along two axes: **milestones** — fixed facts the system and
neighbours rely on (placed, paid, received, cancelled…) — and **statuses**, the shop's own stages,
which the buyer sees grouped into public ones. Dictionary `webx-orders` (`module`, `statuses`,
`panel`, `screen`, `site`, `notifications`, `checkout`), ten languages.

## What it owns

- **The service** `WebxUi\Orders\Orders` — the one door to an order (spec §5.3):
  - `place(PlaceOrder)` — the cart counted with `Purpose::Order` under the lock of its row (`CartService::lock()`), checked,
    frozen into the order and emptied (`CartService::empty()`: the cart's row, cookie and draft stay), one transaction.
    `PlaceOrder`: `cart`, `locale`, `customer` (`Contact`: `firstName`, `lastName`, `phone`, `email` — a first name and a phone
    or an e-mail), `recipient`, `comment`, `expectedPayable`, `awaitPayment`, `blocks`, `idempotencyKey`, `userId`, `source`.
    Refusals: `ValidationException` (contacts), `CartNotOrderable` (`empty`, `currency`, `lines` with the cart's warnings),
    `PayableChanged` (the fresh `CartState`), `OrderRefused` (a listener), `Blocks\BlockRefused`;
  - `findByIdempotencyKey($key)` — the order a form token made; a repeat of `place()` returns it;
  - every other method is one transaction with the order's row locked, events after the commit, and refreshes the model passed
    in. `mark(order, Milestone, source, at?, reference?, note?)` — `paid`, `received`, `cancelled` once (`MilestoneMark`:
    `marked`, `already_marked` with the first mark, `refunded`); others — `MilestoneRefused`; paid or received of a cancelled
    order — `OrderCancelledAlready`; `cancelled` marks the tenders left given back;
  - `paymentFailed(order, source, reason?)` — a history line and `payment_failed_at`; `stopAwaitingPayment(order, source)` — the
    flag down, a line, no event (`OrderPaidAlready`, `OrderCancelledAlready`);
  - `moveTo(order, OrderStatus, source, note?, dryRun?)` → `StatusMove` (`from`, `to`, `publicFrom`, `publicTo`,
    `publicChanged`, `moved`, `milestone`, `notifications`, `refusal`); a refusal (`inactive`, `deleted`, `cancelled`) throws
    `StatusMoveRefused`, a dry run answers it; into a status that sets `cancelled`, `panel` and `mcp` give the reason in `note`
    (`ValidationException` under `note`, on a dry run too);
  - `patchBlock(order, block, patch, source)`, `note(order, text, source)`, `updateDetails(order, OrderDetails, source)` —
    contacts, recipient, comment, manager note;
  - `cancel(order, source, reason?, dryRun?)` → `MilestoneMark` with `wasPaid`, `moneyToRefund`, `tendersReturned`: the tenders
    left marked given back, money not; `panel` and `mcp` must give a reason (`ValidationException`);
  - `refund(order, RefundRequest, dryRun?)` → `RefundResult` — money given back outside the site, `manual` only, no gateway.
    `RefundRequest`: `amount`, `tenders` (id → amount), `items` (id → qty), `reference`, `reason`, `source`; the manager splits
    it, each part ≤ what is left (`RefundRefused`: `empty`, `amount`, `tender`, `qty`, with `field`). Money and every tender
    back → milestone `refunded`, once; lines and totals never change;
  - `linkVisitor(order, userId, source, dryRun?)`, `unlinkVisitor(order, source, dryRun?)` → `VisitorLink` (`linked`,
    `unlinked`, `unchanged`; `visitor`, `previous`): only `user_id`, a history line `visitor`, `OrderDetailsChanged`
    (`fields: ['user_id']`); `VisitorRefused` — no such account. `forgetVisitor(userId, source)` — on `VisitorDeleting`.
- **The statuses** `WebxUi\Orders\OrderStatuses` (§5.6): `writePublic`, `removePublic(…, moveTo?)`, `restorePublic`, `write`,
  `remove`, `restore`, `arrange(publics, groups)` — each with `dryRun` → `StatusPlan` (`orders`, `rebound`, `active`);
  `StatusRefused`: `initial`, `last_active`, `public_deleted`, `has_active`. Switching a stage off is held like deleting it.
- **Events** `Events\OrderPlacing` (in the transaction: spend, reserve, or throw `OrderRefused`); after the commit, with
  `Data\OrderData`, `source`, `adminId`, `at`: `OrderPlaced`, `OrderPaid`, `OrderReceived`, `OrderCancelled` (`wasPaid`,
  `tendersReturned`), `OrderRefunded` (`RefundData`, `full`), `OrderPaymentFailed`, `OrderStatusChanged`,
  `OrderPublicStatusChanged` (only when the public one changes), `OrderBlockUpdated`, `OrderDetailsChanged` (`fields`,
  `before`).
- **Notifications** (§10, with `webx-ui/module-notifications`): seven `orders.*` events (`Notifications\OrderEvents`,
  `OrderValues`, `OrderTemplates`); channels — `orders.notify.*` for a milestone, `options.notify_channels` of a public status;
  a move inside a group tells nobody. `migrate` makes the starting set once (`DefaultChannels`): «Order accepted», «Payment
  received» to `customer_email`, «New order» to the first contacts e-mail. `{{status}}` is `internal`.
- **Journal** `orders.order`: what `updateDetails()` and the links changed. **Blocks** `OrderBlocks` —
  `register(new BlockDefinition('delivery'|'payment', module, validate, summary))` in `boot()`.
- **Config** `webx-orders`: `number` (`prefix`, `start` = 1000, `pad`), `initial_status`, `initial_status_awaiting_payment`
  (ids; null — «New» and «Awaiting payment» of the default set), `notify.*`, `layout`, `middleware`, `account_per_page`,
  `checkout.*`. `OrderSettings` reads what `orders.settings` saved in `module-settings`, then the config. `webx:doctor`:
  `Doctor\OrdersNotificationsCheck`.
- **Tables**: `orders` (`Models\Order` — money in minor units, names apart, `recipient`, blocks as json, a copy of the
  milestones for the filters); `order_items` (a child's `qty` is absolute), `order_adjustments`, `order_tenders`,
  `order_benefits`, `order_milestones` (unique per milestone), `order_refunds`, `order_status_changes` (`StatusChange`, the
  history); `order_public_statuses` (`PublicStatus`), `order_statuses` (`OrderStatus`), soft-deleted.
- **Enums** `Milestone` (`placed`, `awaiting_payment`, `paid`, `received`, `cancelled`, `refunded`; `settableByStatus()` — the
  middle three) and `StatusColor` (`neutral`, `info`, `success`, `warning`, `danger`, `accent` → `--wx-*` tokens).
- **The default statuses**, seeded once from `lang/*/statuses.php` in every language of the site: «Accepted», «In progress»,
  «Shipped», «Completed» (`received`), «Cancelled» (`cancelled`), eight stages; then the site's.
- **The buyer's page** (§13.1) `/order/{number}` (`webx.orders.order`, `.localised`): a signed link without an end,
  `Site\OrderLinks::page(number, locale)`; bad signature — 404; `no-store`. View `webx-orders::order.show` (`order.body`),
  layout `webx-orders.layout` (handle `orders.order`), `resources/theme/orders.css`; cache group `order-statuses`.
- **With `webx-ui/module-users`** (`Site\VisitorOrders`): «My orders» `account/orders[/{number}]` (`webx.orders.account.*`,
  another visitor's order — 404) with the badge of orders in progress, the warning of `DeletionWarnings`, the tab `orders-tab`
  (`wx-orders-list`) and `orders-count` on `users.form`, the card `visitor-card` on `orders.show`; panel `{id}/visitors` (+
  `users.view`), `{id}/visitor` POST / DELETE; MCP `orders_link_visitor`. Without it none of these exist.
- **The section** `orders` (`Panel\OrdersModule`, npm `@webx-ui/module-orders`): `api/cms/orders` — the list (views, search,
  filters, `user`, `counts`), `meta`, the card, `{id}/status|milestones| cancel|details|refunds`, the selection's `status` and
  `cancel`; every write a call of `Orders`, `dry_run` the service's own. The card `orders.show`: public ids `header-actions`,
  `tabs`, `summary`, `customer`; fields `customer.*`, `recipient.*`, `comment`, `manager_note`. Role `orders` («Order manager»).
  `statuses` and `settings` — under `orders.statuses` (`settings` also `orders.checkout.settings`). The selection:
  `Panel\StatusBatch`.
- **MCP** (§12, `Mcp\OrdersTools`, `Mcp\StatusTools` on `OrdersModule`): `orders_list|get|history|summary`, `orders_set_status`
  (`order` or `numbers` ≤ 200), `orders_mark|cancel|refund|update|note`, `orders_statuses_list|save|delete|restore|reorder`,
  `orders_public_statuses_save|delete|restore|reorder`, `orders_settings_get|set`; resource `orders://catalog`. Source `mcp`;
  money in minor units with `*_formatted`; a dry run is the panel dialog's answer (`Consequences`, `StatusBoard`,
  `StatusBatch`), rolled back where the service has none.

### Checkout

- **Switch** `webx-orders.checkout.enabled` (`WEBX_ORDERS_CHECKOUT`, on; `Checkout\Checkout::enabled()`). Off — no checkout
  routes, drafts, tools, prune or tab; `Orders::place()` from code works. Also `path`, `layout`, `empty_url`, `draft_days`.
- **Draft** `order_checkout_drafts` (`Checkout\Models\CheckoutDraft`, key `cart_id`, gone with the cart), written only by a
  checkout `POST`; the cart's `CartsMerging` at sign-in moves the guest's to the account's cart that has none.
  `webx:orders:checkout-prune [--days=] [--dry-run]`, daily — older than `draft_days` (30), cart or not.
- **Tab «Checkout»** of `orders.settings` (`OrderSettings::CHECKOUT` — defaults): `orders.checkout.fields.*` (`required`,
  `optional`, `hidden`; phone or e-mail stays `required`), `recipient`, `comment`, `terms`, `terms_page`, `privacy_page`.
- **Page** `/checkout` (route `checkout` — the cart's button; also behind `{locale}/`), `Checkout\Http\CheckoutController`:
  `POST steps/{key}` keeps a step in the draft, `GET summary` — the column, `POST place` → `Orders::place()` with
  `expected_payable`. `Checkout\Steps\CheckoutSteps`: `contacts` 100, `comment` 900, a project's `Step` by position;
  `CheckoutContext` — the visitor's cart counted once with `Purpose::Checkout`. A `GET` never makes a draft.

### Permissions

| Permission                 | Opens                                                     |
| -------------------------- | --------------------------------------------------------- |
| `orders.view`              | the list, the card and the history of orders              |
| `orders.manage`            | statuses, marks, cancelling, contacts, notes, the visitor |
| `orders.refund`            | recording money given back                                |
| `orders.statuses`          | the screens of statuses and the settings of the section   |
| `orders.checkout.settings` | the tab «Checkout» of the settings                        |

Routes ask with `cms.can`, tools with `permission:`: reading — any of the first three; writing as
the table says. Finding a visitor to link (`{id}/visitors`) wants `users.view` as well.

## Change it without forking

| You want                             | Do                                                                                                  |
| ------------------------------------ | --------------------------------------------------------------------------------------------------- |
| other words for the default statuses | `php artisan vendor:publish --tag=webx-orders-lang` before `migrate`, or rename the rows afterwards |
| other statuses                       | «Orders → Statuses», or `OrderStatuses` in code — they are the site's own                           |
| to react to an order                 | listen to `OrderPlaced`; to do it with the order or not at all — `OrderPlacing`                     |
| a letter or a message about an order | a channel of an `orders.*` event in «Notifications», chosen in Settings or on a public status       |
| a tab of yours on the card           | `Screens::extend('orders.show', [['op' => 'add', 'target' => 'tabs', 'node' => […]]])`              |
| a button beside the status           | the same with `'target' => 'header-actions'`                                                        |

## Do not

- Do not write the order tables yourself, not even from a listener: every change goes through `Orders` (decision 18), which
  keeps the history, the milestone copies and the events together.
- Do not do anything slow in `OrderPlacing` (HTTP, letters): the cart's row is locked meanwhile.
- Do not delete an order (cancel it), nor link a visitor by writing `user_id` (`Orders::linkVisitor()`).
- Do not delete a status row for good: orders point at it. Soft-delete it — `OrderStatuses::remove()`.
- Do not store a hex colour in `order_statuses.color`: it is a `StatusColor` name for the dark theme.

## Check your work

- `Panel/*Test` — `BoundariesTest`: the panel never writes an order itself; `PerformanceTest`: the list's budget;
  `AccessCoverageTest`. `CancelByStatusTest`.
- `Mcp/McpTest` (parity with the panel, dry runs write nothing), `Mcp/PerformanceTest`, `Mcp/AccessCoverageTest`,
  `Mcp/LinkVisitorTest`; `webx:mcp-tools --module=orders` lists 21 (22 with users).
- `Site/OrderPageTest` (the signed page, its budget, nothing without users), `Site/VisitorOrdersTest`.
- `PlaceTest`, `MilestonesTest`, `StatusMoveTest`, `DetailsTest`, `RefundTest`, `PerformanceTest` (the budget of `place()`), and
  `CartLockTest`, `MilestoneRaceTest` against a real server:
  `WEBX_TEST_MYSQL_URL=mariadb://user:password@host:3306/db vendor/bin/phpunit …`.
- `migrate` / `migrate:rollback` — the tables come and go. `Notifications/*Test` — with and without it.
- `Checkout/DraftsTest`, `CheckoutDisabledTest`, `CheckoutWithoutNeighboursTest` (plain forms), `CheckoutFieldsTest`.

## Read more

- `README.md` beside this file; spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_ORDERS.md
