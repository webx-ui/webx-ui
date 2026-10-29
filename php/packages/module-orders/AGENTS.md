# webx-ui/module-orders

Orders, and — in later releases — the checkout that places them. An order is a snapshot of the
cart (`webx-ui/module-cart`) at placing: lines, prices, discounts, tenders, benefits, the contacts
of the buyer and the recipient, and the blocks delivery and payment write. After that it depends
on no live record. It moves along two axes: **milestones** — fixed facts the system and
neighbours rely on (placed, paid, received, cancelled…) — and **statuses**, the shop's own stages,
which the buyer sees grouped into public ones.

This release holds the tables, the models, the default statuses and the section of the panel;
the `Orders` service, the screens, MCP tools and the checkout come next.

## What it owns

- **Tables** (all `order_*` but `orders`):
  - `orders` (`WebxUi\Orders\Models\Order`) — money in minor units of `currency`, contacts with
    the first and the last name apart, `recipient`, `delivery` and `payment` blocks (json), and a
    copy of the milestones (`placed_at`, `awaiting_payment`, `paid_at`, …) for the filters;
  - `order_items` (`OrderItem`, a child's `qty` is absolute), `order_adjustments`,
    `order_tenders`, `order_benefits`, `order_milestones` (`OrderMilestone`, unique per order and
    milestone), `order_refunds`, `order_status_changes` (`StatusChange`, the history);
  - `order_public_statuses` (`PublicStatus`: `code`, `name`, `description`) and `order_statuses`
    (`OrderStatus`: `public_status_id`, `name`, `color`, `sets_milestone`, `is_active`), both
    soft-deleted.
- **Enums** `WebxUi\Orders\Milestone` (`placed`, `awaiting_payment`, `paid`, `received`,
  `cancelled`, `refunded`; `settableByStatus()` — only `paid`, `received`, `cancelled`) and
  `WebxUi\Orders\StatusColor`, the closed palette of a status (`neutral`, `info`, `success`,
  `warning`, `danger`, `accent`) the panel turns into `--wx-*` tokens.
- **The default statuses**, seeded once by the migration from `lang/*/statuses.php` in every
  language of the site: «Accepted» (New, Awaiting payment), «In progress» (Taken into work,
  Waiting for a call, Being packed), «Shipped» (Handed to delivery), «Completed» (Completed —
  sets `received`), «Cancelled» (Cancelled — sets `cancelled`). After that they are the site's.
- **The dictionary** `webx-orders` (`module`, `statuses`), ten languages.
- **The section** `orders` of the panel (`Panel\OrdersModule`), npm half `@webx-ui/module-orders`.

### Permissions

| Permission        | Opens                                                        |
| ----------------- | ------------------------------------------------------------ |
| `orders.view`     | the list, the card and the history of orders                 |
| `orders.manage`   | statuses, milestones by hand, cancelling, contacts and notes |
| `orders.refund`   | recording money given back                                   |
| `orders.statuses` | the screens of statuses and the settings of the section      |

No route or tool asks for them yet: the screens and the MCP tools that do come in later releases.

## Change it without forking

| You want                             | Do                                                                                                  |
| ------------------------------------ | --------------------------------------------------------------------------------------------------- |
| other words for the default statuses | `php artisan vendor:publish --tag=webx-orders-lang` before `migrate`, or rename the rows afterwards |
| other statuses                       | rows of `order_statuses` / `order_public_statuses` — they are the site's own                        |
| to react to an order                 | wait for the events of the `Orders` service; do not watch the tables                                |

## Do not

- Do not write the order tables yourself, not even from a listener: every change of an order goes
  through the `Orders` service (spec decision 18), which keeps the history, the milestone copies
  and the events together. Until it exists, read the models only.
- Do not delete an order: it is the document of a deal. Cancel it instead.
- Do not delete a status row for good: orders keep pointing at it and the history shows its name.
  Soft-delete it (`delete()`), which leaves the orders where they are.
- Do not store a hex colour in `order_statuses.color`: it is a name from `StatusColor`, so the
  dark theme of the panel can read it.

## Check your work

- `php artisan migrate` and `php artisan migrate:rollback` — the tables come and go.
- After `migrate`, `order_public_statuses` holds five rows and `order_statuses` eight, each
  `name` with a key for every language of the site (`php artisan tinker`,
  `WebxUi\Orders\Models\OrderStatus::all()->pluck('name')`).

## Read more

- `README.md` beside this file.
- Spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_ORDERS.md
