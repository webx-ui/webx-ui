# @webx-ui/module-payments

The front end of the payments section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: the methods a shop offers in the checkout with their gateway keys and a connection test, the
journal of payment attempts, the raw journal of the exchange with gateways, and the tab «Payment»
of an order's card.

The other half is the Composer package `webx-ui/module-payments`, which owns the tables, the
registries of method types and gateways, the exchange journal and the API. A section appears in the
panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-payments
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { payments } from '@webx-ui/module-payments'
import '@webx-ui/module-payments/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...payments()],
}).mount()
```

`payments()` is **three** sections — **Methods**, **Attempts** and **Gateway exchange** — in the
menu group «Payments», so it is spread into the list. The first one also brings the node
`wx-payments-order`, the tab the server patches onto the order's card.

## What it never shows

A gateway's key never reaches the browser: the form knows only whether each key is set. The headers
and bodies of the exchange come only for an administrator with `payments.exchange`; without it an
event is its time, kind, code, signature, decision and sizes.

## License

MIT
