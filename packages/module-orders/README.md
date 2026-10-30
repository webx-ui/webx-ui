# @webx-ui/module-orders

The front end of the orders section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: the orders placed on the site, their statuses and milestones, and — later — the settings of
the checkout.

The other half is the Composer package `webx-ui/module-orders`, which owns the orders, the
statuses, the `Orders` service and the checkout. A section appears in the panel when both halves
are installed.

## Install

```bash
npm install @webx-ui/module-orders
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { orders } from '@webx-ui/module-orders'

createAdmin({
  basePath: '/cms',
  modules: [orders()],
}).mount()
```

`orders({ path: '/sales' })` puts the section somewhere else inside the panel.

## Status

The first release is the section and an empty list. The list, the card, the statuses and the
settings come with the next ones; the spec is
[`WEBX_UI_MODULE_ORDERS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_ORDERS.md).

## License

MIT
