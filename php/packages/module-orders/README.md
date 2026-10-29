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

## Status

This release is the package and its tables. The `Orders` service, the screens of the panel, the
MCP tools, the buyer's page and the checkout come in the next ones; the spec is
[`WEBX_UI_MODULE_ORDERS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_ORDERS.md).

## License

MIT
