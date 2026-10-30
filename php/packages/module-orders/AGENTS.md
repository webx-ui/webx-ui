# webx-ui/module-orders

Orders and the checkout that places them. An order is a snapshot of the cart (`webx-ui/module-cart`) at placing: lines,
prices, discounts, tenders, benefits, the contacts of the buyer and the recipient, and the blocks delivery and payment
write. After that it depends on no live record. It moves along two axes: **milestones** — fixed facts the system and
neighbours rely on (placed, paid, received, cancelled…) — and **statuses**, the shop's own stages, which the buyer sees
grouped into public ones. Dictionary `webx-orders` (`module`, `statuses`, `panel`, `screen`, `site`, `notifications`,
`checkout`), ten languages.

## What it owns

- **The service** `WebxUi\Orders\Orders` — the one door to an order (spec §5.3):
  - `place(PlaceOrder)` — the cart counted with `Purpose::Order` under the lock of its row (`CartService::lock()`), checked,
    frozen into the order and emptied (`CartService::empty()`: the cart's row, cookie and draft stay), one transaction.
    `PlaceOrder`: `cart`, `locale`, `customer` (`Contact`: `firstName`, `lastName`, `phone`, `email` — a first name and a phone
    or an e-mail), `recipient`, `comment`, `expectedPayable`, `awaitPayment`, `blocks`, `idempotencyKey`, `userId`, `source`.
    Refusals: `ValidationException` (contacts), `CartNotOrderable` (`empty`, `currency`, `lines` with the cart's warnings),
    `PayableChanged` (the fresh `CartState`), `OrderRefused` (a listener), `Blocks\BlockRefused`;
  - `findByIdempotencyKey($key)` — the order a form token made; a repeat of `place()` returns it; `placeByManager(ManagerOrder, source = 'panel')` (§29.2) — the manager's lines into a service cart (`CartService::add(null, …)`), `place()`, `clear()`, one transaction; `previewByManager(input, contacts = true)` → `ManagerPreview` (counted for the order, rolled back; `false` — the lines alone);
  - every other method is one transaction with the order's row locked, events after the commit, and refreshes the model passed
    in. `mark(order, Milestone, source, at?, reference?, note?, amount?)` — `paid`, `received`, `cancelled` once (`MilestoneMark`:
    `marked`, `already_marked` with the first mark, `refunded`); others — `MilestoneRefused`; paid or received of a cancelled
    order — `OrderCancelledAlready`; `cancelled` marks the tenders left given back;
  - `paymentFailed(order, source, reason?)` — a history line and `payment_failed_at`; `stopAwaitingPayment(order, source)` / `awaitPayment(order, source)` — the flag down / up, a line, no event (`OrderPaidAlready`, `OrderCancelledAlready`);
  - **balance** (§36, decision 102): `balance(order)` → `Balance` (`due`, `over`, `kind()` — `topup`, `unpaid`, `over`); `orders.paid_total` and the stored `balance = due − over` (a refund never makes it owe), written by each method that moves a term; `recordPayment(order, amount, source, reference?, at?, note?, dryRun?)` → `PaymentRecord` — a top-up of a paid order, line `payment`, `OrderPaymentReceived` (`PaymentRefused`: `not_paid`, `cancelled`); a refund's limit is `max(payable, paid_total) − refunded_total`;
  - `moveTo(order, OrderStatus, source, note?, dryRun?)` → `StatusMove` (`from`, `to`, `publicFrom`, `publicTo`,
    `publicChanged`, `moved`, `milestone`, `notifications`, `refusal`); a refusal (`inactive`, `deleted`, `cancelled`) throws
    `StatusMoveRefused`, a dry run answers it; into a status that sets `cancelled`, `panel` and `mcp` give the reason in `note`
    (`ValidationException` under `note`, on a dry run too);
  - `patchBlock(order, block, patch, source)`, `note(order, text, source)`, `updateDetails(order, OrderDetails, source)` — contacts, recipient, comment, manager note;
  - **lines** (§37, decision 101 — variant A): `editItems(order, ItemsEdit, dryRun?)` → `ItemsEditResult` (`changes` — `added()`, `changed()`, `removed()`; `before`, `after`, `balance`, `warnings`, `revision`). `ItemsEdit`: `revision` seen, `add` (as `CartService::add()`, a service cart like `placeByManager()`), `qty` (id → qty, 0 takes it out), `remove`, `reason` (optional, decision 103), `message`, `notify`, `source`. The lines placed keep price and their discount in proportion, a line added — the price now, no rules; order discounts, delivery, benefits, milestones stay; a line taken out keeps `qty = refunded_qty` and `removed_at`; a bundle scales whole. `ItemsEditRefused`: `stale`, `nothing`, `empty`, `state` (received, cancelled, refunded), `child`, `generated`, `below_refunded`, `foreign`; a line added — `ValidationException` under `add.N.*`, `CartNotOrderable`. Row `order_revisions`, history line `items` (`revision#N[: reason]`);
  - `cancel(order, source, reason?, dryRun?)` → `MilestoneMark` (`wasPaid`, `moneyToRefund`, `tendersReturned`, `move`): tenders
    given back, money not, the order into the first active cancelling status (`move`, null — none); `panel`/`mcp` give a reason;
  - `refund(order, RefundRequest, dryRun?)` → `RefundResult` — money given back outside the site, `manual` only, no gateway.
    `RefundRequest`: `amount`, `tenders` (id → amount), `items` (id → qty), `reference`, `reason`, `source`; the manager splits
    it, each part ≤ what is left (`RefundRefused`: `empty`, `amount`, `tender`, `qty`, with `field`). Money and every tender
    back → milestone `refunded`, once; lines and totals never change. `reverseRefund(order, refundId, reason, source, dryRun?)` → `RefundReversal` (decision 98): a mistaken refund is reversed, never edited — the row stays with `reversed_*`, its sums come off, `refunded` comes off once the order is no longer given back whole (the one milestone ever taken back); `RefundReversalRefused`: `foreign`, `reversed`; `panel`/`mcp` give a reason;
  - `linkVisitor(order, userId, source, dryRun?)`, `unlinkVisitor(order, source, dryRun?)` → `VisitorLink` (`linked`,
    `unlinked`, `unchanged`; `visitor`, `previous`): only `user_id`, a history line `visitor`, `OrderDetailsChanged`
    (`fields: ['user_id']`); `VisitorRefused` — no such account. `forgetVisitor(userId, source)` — on `VisitorDeleting`.
- **The statuses** `WebxUi\Orders\OrderStatuses` (§5.6): `writePublic`, `removePublic(…, moveTo?)`, `restorePublic`, `write`,
  `remove`, `restore`, `arrange(publics, groups)` — each with `dryRun` → `StatusPlan` (`orders`, `rebound`, `active`);
  `StatusRefused`: `initial`, `last_active`, `public_deleted`, `has_active`. Switching a stage off is held like deleting it.
- **Events** `Events\OrderPlacing`, `OrderItemsChanging` (`order`, `revision`) (in the transaction: spend, reserve, or throw `OrderRefused`); after the commit, with
  `Data\OrderData`, `source`, `adminId`, `at`: `OrderPlaced`, `OrderPaid`, `OrderReceived`, `OrderCancelled` (`wasPaid`,
  `tendersReturned`), `OrderRefunded` (`RefundData`, `full`), `OrderRefundReversed` (`reason`, `milestoneRemoved`), `OrderPaymentFailed`, `OrderPaymentReceived`, `OrderItemsChanged` (`RevisionData`, `balance`, `notify`), `OrderStatusChanged`,
  `OrderPublicStatusChanged` (only when the public one changes), `OrderBlockUpdated`, `OrderDetailsChanged` (`fields`, `before`).
- **Notifications** (§10, §40.2, with `webx-ui/module-notifications`): nine `orders.*` events (`Notifications\OrderEvents`,
  `OrderValues`, `OrderTemplates`); channels — `orders.notify.*` for a milestone and the edits, `options.notify_channels` of a public status;
  a move inside a group tells nobody. `migrate` makes the starting set once (`DefaultChannels`, the edits' two apart): «Order accepted», «Payment received», «Order changed» (`items_changed` — only with `ItemsEdit::notify`; `changes`, `previous_total`, `balance*`, `amount_due|over`, `message`, never `reason`), «Payment due» (`payment_requested`, by hand only, while `due > 0`) to `customer_email`,
  «New order» to the first contacts e-mail. `{{status}}` is `internal`. By hand (§10.3): `Orders::notify()`, `orders_notify`; `NotifyRefused::label()` — the words.
- **Journal** `orders.order`: what `updateDetails()` and the links changed. **Blocks** `OrderBlocks` —
  `register(new BlockDefinition('delivery'|'payment', module, validate, summary))` in `boot()`.
- **Config** `webx-orders`: `number` (`prefix`, `start` = 1000, `pad`), `initial_status`, `initial_status_awaiting_payment`
  (ids; null — «New» and «Awaiting payment» of the default set), `notify.*`, `layout`, `middleware`, `account_per_page`,
  `checkout.*`. `OrderSettings` reads what `orders.settings` saved in `module-settings`, then the config. `webx:doctor`:
  `Doctor\OrdersNotificationsCheck`.
- **Tables**: `orders` (`Models\Order` — money in minor units, names apart, `recipient`, blocks as json, a copy of the
  milestones for the filters); `order_items` (a child's `qty` is absolute), `order_adjustments`, `order_tenders`,
  `order_benefits`, `order_milestones` (unique per milestone), `order_refunds`, `order_revisions` (`OrderRevision`, the edits), `order_status_changes` (`StatusChange`, the
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
- **The section** `orders` (`Panel\OrdersModule`, npm `@webx-ui/module-orders`): `api/cms/orders` — the list (views, `view=auto` — «New» or «All», search,
  filters — `balance=due|over` by the index `(balance, placed_at)`, `user`, `counts`), `meta`, `summary` (= `orders_summary`, `Panel\OrdersSummary`; today / 7 / 30 calendar days in `app.timezone`), `{id}/glance` (an opened row), the card, `{id}/status|milestones|cancel|details|notes|payments|refunds|refunds/{refund}/reverse`, «Change the lines» `{id}/items/preview` (dry run) and PUT `{id}/items` (`ItemsController`, `editItems()`; `stale` — under `revision`), the selection's `status` and
  `cancel`, «New order» `new` (`Http\Controllers\Panel\NewOrderController`: `new/products` — `Purchasables::find()`, `new/preview`, POST — `placeByManager()`, `panel`); every write a call of `Orders`, `dry_run` the service's own. The card `orders.show`: public ids `header-actions`,
  `tabs`, `order-notes` (notes = history lines `note`, `Orders::note()`; over them the pinned `manager_note`, saved by `details`), `summary`, `customer`; fields `customer.*`, `recipient.*`, `comment`. Role `orders` («Order manager»).
  `statuses` and `settings` — under `orders.statuses` (`settings` also `orders.checkout.settings`). The selection: `Panel\StatusBatch`.
- **MCP** (§12, `Mcp\OrdersTools`, `Mcp\StatusTools` on `OrdersModule`): `orders_list|get|history|summary`, `orders_set_status`
  (`order` or `numbers` ≤ 200), `orders_mark|payment_record|items_edit|cancel|refund|refund_reverse|notify|update|create|note` (`orders_history` — `revisions`), `orders_statuses_list|save|delete|restore|reorder`,
  `orders_public_statuses_save|delete|restore|reorder`, `orders_settings_get|set`; resource `orders://catalog`. Source `mcp`;
  money in minor units with `*_formatted`; a dry run is the panel dialog's answer (`Consequences`, `StatusBoard`,
  `StatusBatch`), rolled back where the service has none.

### Checkout

- **Switch** `webx-orders.checkout.enabled` (`WEBX_ORDERS_CHECKOUT`, on; `Checkout\Checkout::enabled()`). Off — no checkout
  routes, drafts, tools, prune or tab; `Orders::place()` from code works. Also `path`, `layout`, `empty_url`, `draft_days`.
- **Draft** `order_checkout_drafts` (`CheckoutDraft`, key `cart_id`), only by a `POST`; `CartsMerging` moves the guest's to an account's cart without one. `webx:orders:checkout-prune [--days=] [--dry-run]`, daily — older than `draft_days` (30). `last_step` `confirm` — it placed an order.
- **Tab «Checkout»** of `orders.settings` (`OrderSettings::CHECKOUT` — defaults): `orders.checkout.fields.*` (`required`, `optional`, `hidden`; phone or e-mail stays `required`), `recipient`, `comment`, `terms`, `terms_page`, `privacy_page`, `captcha` (`off`; `on` — the site's captcha, refused while the site has none), `quick.enabled` (`off`), `quick.message` (a language each).
- **Page** `/checkout` (route `checkout` — the cart's button; also behind `{locale}/`), `Checkout\Http\CheckoutController`:
  `POST steps/{key}` keeps a step in the draft, `GET summary` — the column; `CheckoutSteps`: `contacts` 100, `comment` 900,
  a project's `Step` by position; `CheckoutContext` — the cart counted once. A `GET` never makes a draft.
- **Slots** (§22) `Checkout\Slots\CheckoutSlots`, registered by their modules: `delivery` 200 (`ShippingSlot`: `totalsLine()`
  — «Delivery cost», `choice()` → `CheckoutContext::shippingChoice()`), `payment` 300 (`PaymentSlot`: `awaitsPayment()`,
  `next()` — a gateway after the commit, `payAgainUrl()` — «Pay»). `SlotStep` wraps one: a throw — «could not load», no order.
- **Confirm** `POST place`: antispam (`webx_hp`, `webx_ts`, origin, `throttle:webx-orders-checkout`, the captcha — `captcha` 422) → `place_key` (a
  repeat is the same order) → steps → `Orders::place()`; came to less — placed again with it. `PlacingListener` (only that
  order) notes the agreement, clears the draft but the contacts. `GET thanks/{number}` — for the session that placed it.
- **View** (§27): composition `checkout.page` (ids `checkout`, `.main`, `.steps`, `.aside`, `.summary`, `.summary.parts` —
  `CheckoutSummaryParts`, `.trust`, `.sticky`) in `CheckoutLayout` (`layout.minimal`); `data-steps` `open` | `collapse` (theme-default);
  `resources/theme/checkout.css`, `webx/orders/checkout.js` (redraws, `webx:users:signed-in` cancelled); «Thank you» — an account.
- **Events** (§30, `Checkout\Events`): `CheckoutStepSaved(cartId, step)`, `CheckoutPayableChanged(cartId, from, to)` (the draft counts `payable_changes`), `CheckoutPlaced(orderId, cartId)` after the commit; the order's own — `OrderPlaced`.
- **MCP** (§32, `Mcp\CheckoutTools`, only while on): `orders_checkout_steps|preview|funnel|drafts_list|settings_get|settings_set`, `orders://checkout`; `preview` asks what `place` asks (`CheckoutPreview`, `CheckoutAnswers`), writes nothing; a draft's contacts come masked (`ContactMask`).
- **One click** (§29): `<x-webx-orders::checkout.quick-button type= ref= />` → `POST checkout/quick` (`QuickController`; no CSRF — cached pages) → `QuickOrders`: a service cart of one line (`CartService::add(null, …)`, no cookie), `Orders::place(source: 'quick')`, `clear()` — one transaction, the buyer's cart and draft untouched. Phone required; no name — «Buyer» (`checkout.quick.anonymous`); 3 an hour an IP and a phone (`throttle.quick_per_hour`), the captcha by the same setting. The list filters `source` (milestone `placed`).

### Permissions

| Permission                 | Opens                                                                                                                                                          |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `orders.view`              | the list, the card and the history of orders                                                                                                                   |
| `orders.manage`            | statuses, marks, cancelling, contacts, notes, the visitor, a new order (`orders_create`), the lines (`orders_items_edit`), a message by hand (`orders_notify`) |
| `orders.refund`            | recording money given back                                                                                                                                     |
| `orders.statuses`          | the screens of statuses and the settings of the section                                                                                                        |
| `orders.checkout.settings` | the tab «Checkout», `orders_checkout_settings_set`                                                                                                             |

Routes ask with `cms.can`, tools with `permission:`: reading — any of the first three; writing as the table says. Finding a visitor to link (`{id}/visitors`) wants `users.view` as well.

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

- `Panel/*Test` — `BoundariesTest`: the panel never writes an order itself; `PerformanceTest`: the list's budget; `AccessCoverageTest`; `NewOrderTest` (search, live totals, `panel`); `ListGlanceTest` (the default tab, an opened row, the pinned note). `CancelByStatusTest`.
- `Mcp/McpTest` (parity with the panel, dry runs write nothing), `Mcp/SummaryTest` (the list's figures = `orders_summary`), `Mcp/SummaryDaysTest` (midnight of the site), `Mcp/CheckoutToolsTest` (preview = `place`, masks), `Mcp/PerformanceTest`, `Mcp/AccessCoverageTest`, `Mcp/LinkVisitorTest`, `Mcp/CreateOrderTest`, `Mcp/ItemsEditToolTest` (editor = `orders_items_edit`, `stale`); 32 tools (33 with users, 26 with the checkout off).
- `Site/OrderPageTest` (the signed page, its budget, an edit's mark and balance, nothing without users), `Site/VisitorOrdersTest`. `Notifications/*Test` — with and without it, `EditNotificationsTest` — the edits' letters; `ResendTest` — by hand, the second press.
- `PlaceTest`, `BalanceTest` (the column = the formula after each method), `MilestonesTest`, `StatusMoveTest`, `DetailsTest`, `RefundTest`, `RefundReversalTest`, `ManagerOrderTest`, `ItemsEditTest` (variant A, refusals, rollback, dry run), `PerformanceTest` (the budgets of `place()` and `editItems()`), and
  `CartLockTest`, `MilestoneRaceTest` against a real server: `WEBX_TEST_MYSQL_URL=mariadb://user:password@host:3306/db`; `migrate` / `migrate:rollback`.
- `Checkout/*Test`: `CheckoutWithoutNeighboursTest`, `PlaceIdempotencyTest`, `PayableChangedTest`, `PerformanceTest` (`GET /checkout`),
  `PlacePerformanceTest`, `StepOrderTest`, `CheckoutSlotsTest`, `CheckoutViewTest`, `CheckoutSignInTest`, `CheckoutDisabledTest`, `QuickOrderTest`; a slot extends `SlotContractTestCase`.

## Read more

- `README.md` beside this file; spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_ORDERS.md
