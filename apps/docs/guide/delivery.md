# Delivery

`webx-ui/module-delivery` is the delivery of a shop on WebX UI: which methods a buyer is offered
in the town they picked, at what price, and what each method asks for — a point to pick the
parcel up at, or an address. The shop's own pick-up points and its own courier are built in; a
carrier comes as a satellite package. The towns themselves are the shared geography of
`webx-ui/geography`, kept by the carriers' sync or by hand — a shop without a single carrier
works on towns made in the panel.

## Install

```bash
composer require webx-ui/module-delivery
php artisan migrate
```

The panel gets the group «Delivery»: methods, the shop's points, carriers, the geography, the
popular towns of the checkout and the binding queue.

## Agents

With the panel and `webx-ui/mcp` installed, an agent runs the delivery through the same doors as
the panel: a method is saved by the method's form, a town by the geography's editor — so a town
an agent makes runs into the same duplicate a town made by hand does. Reading tools are behind
`delivery:read` and `delivery.view`, writing ones behind `delivery:write` and `delivery.manage`;
the parcels' writes behind `delivery.ship`. Every write takes `dry_run`.

| Tool                         | What it does                                                                                                                       |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `delivery_methods_list`      | methods in the checkout's order: key, type, carrier, titles, price, countries, enabled, the privilege                              |
| `delivery_methods_save`      | create a method of a type, or change one by its `key`                                                                              |
| `delivery_methods_reorder`   | the checkout's order of methods                                                                                                    |
| `delivery_quote`             | a trial of the checkout for a town and a cart (`cart_id` or `items`): every method priced, or why not — no points, another country |
| `delivery_points_search`     | points of the shop and the carriers by town, carrier, kind, text and state, a page at a time                                       |
| `delivery_points_save`       | a point of the shop's own: create or change                                                                                        |
| `delivery_points_deactivate` | switch a point of the shop off; a dry run says how many carts hold it                                                              |
| `delivery_localities_search` | the shared towns by a prefix of a name, with their carriers' bindings and how many points                                          |
| `delivery_countries_list`    | the site's countries and the ISO codes to switch one on from                                                                       |
| `delivery_countries_set`     | switch a country on or off, its order, its own name, its leading carrier                                                           |
| `delivery_regions_list`      | the regions of a country, with their ids                                                                                           |
| `delivery_regions_save`      | create, rename or delete an empty region                                                                                           |
| `delivery_localities_save`   | a town by hand: one like an existing one comes back `duplicate: true` with the similar ones; `force` makes it anyway               |
| `delivery_localities_remove` | delete a town nothing holds, or switch off one that points, carts or orders hold; switch one back on                               |
| `delivery_popular_list`      | the popular towns of the dialog «Where to deliver the order?», in order                                                            |
| `delivery_popular_set`       | the whole list in a new order, or one town added or taken away; a dry run answers before and after                                 |
| `delivery_bindings_list`     | carriers' towns no rule placed, with the reasons and the candidates                                                                |
| `delivery_bindings_resolve`  | bind, create a shared town, or ignore — by rows or by a filter                                                                     |
| `delivery_carriers_list`     | carriers, their types and what each can do, settings; keys only as `api_key: set`                                                  |
| `delivery_carriers_update`   | switch a carrier on or off, its settings                                                                                           |
| `delivery_sync_status`       | each directory of each carrier: last success, running, held, stale, counters                                                       |
| `delivery_sync_run`          | run a carrier's sync through the queue; a dry run says what would run                                                              |
| `delivery_sync_confirm`      | confirm the removal a held pass stopped at; a dry run lists what would go                                                          |
| `delivery_shipments_list`    | parcels by order, carrier, status, on the way too long, returned — with `module-orders`                                            |
| `delivery_shipments_create`  | the waybill of an order; a dry run answers the request without asking the carrier                                                  |
| `delivery_shipments_track`   | ask the carriers where the parcels of an order are                                                                                 |

The resource `delivery://catalog` lists the types of methods and what each can do, the kinds of
points, the carriers with their directories, the countries the site delivers to, what the
privilege `shipping.paid_by_store` does and the rules of the sync's removal with its threshold —
read from the registries, so a satellite shows up there once it is installed.

**A carrier's API key never passes through an agent**: no answer carries it and no tool takes it.
Keys are typed in the panel.

When a buyer says a method is missing in their town, start from `delivery_quote` with that town.

## Read more

- The delivery spec — [`WEBX_UI_MODULE_DELIVERY.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_DELIVERY.md).
- [AI agents](./agents.md) — connecting an agent, scopes and permissions.
