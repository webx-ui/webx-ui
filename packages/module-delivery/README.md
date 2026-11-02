# @webx-ui/module-delivery

The front end of the delivery section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: the methods a shop offers in the checkout and their order, the shop's own pick-up points,
the carriers with their directories and keys, the shared geography kept by hand, the popular towns
of the checkout's dialog and the queue of a carrier's localities waiting to be bound.

The other half is the Composer package `webx-ui/module-delivery`, which owns the tables, the
registries of carriers and method types, the sync, the checkout's step and the API. A section
appears in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-delivery
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { delivery } from '@webx-ui/module-delivery'
import '@webx-ui/module-delivery/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...delivery()],
}).mount()
```

`delivery()` is **six** sections — **Methods**, **Own points**, **Carriers**, **Geography**,
**Popular towns** and **Binding queue** — in the menu group «Delivery», so it is spread into the
list. `delivery({ path: '/shipping' })` puts them somewhere else inside the panel.

## Screens

The forms of a method, a point and a locality are described screens — `delivery.method-form`,
`delivery.point-form`, `delivery.locality-form` — so a project adds a field with a patch. The
fields only this section has come with the package and are registered in every screen of the
panel:

| Type                     | Value                                          |
| ------------------------ | ---------------------------------------------- |
| `wx-delivery-locality`   | a locality id; «Add a locality» when not found |
| `wx-delivery-localities` | locality ids, in the order chosen              |
| `wx-delivery-region`     | a region id of the country field beside it     |
| `wx-delivery-country`    | a country code of the site                     |
| `wx-delivery-schedule`   | opening hours: the week and the exceptions     |

`WxDeliveryLocalitySearch` and `WxDeliveryLocalityDialog` are exported for a screen of your own
that picks or makes a locality the same way.

## Permissions

`delivery.view` reads every screen; `delivery.manage` writes them. The keys of a carrier are never
sent to the panel — only whether each one is set.

## License

MIT
