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

## A shop without carriers

Nothing in the delivery needs a carrier. A shop that hands parcels over at its own counters and
drives them out itself is set up in the panel, in this order:

1. **Geography** — switch on the country the shop works in (any ISO code, named by `symfony/intl`),
   make its regions, then its towns: a name in each language of the site, a type (city, town,
   village…), the region, coordinates if you have them, and a weight — the heavier town comes
   first in the suggestions. A town like one already there (same country, region, type and name,
   whatever the apostrophes or dashes) is not made twice: the form shows the similar one and
   offers to pick it.
2. **Own points** — a point of the shop: its kind (a store, a pick-up point, or a kind of your own),
   the town, the address, the phone and the opening hours. «Add a locality» in the town's field
   makes a missing town on the spot and puts it into the point.
3. **Methods** — a «Pick up at a store» method for each kind of point (a store and a pick-up point
   are two methods, each with its own title and price), and «Our courier» with the towns it drives
   to — «Add a locality» works there too — and its one price. Drag them into the checkout's order.
4. **Popular towns** — the buttons of the dialog «Where to deliver the order?»: add the towns most
   buyers pick, drag them into order. An empty list shows the search alone.

A buyer then picks the town in the checkout — by the search or a button — and sees the methods of
that town: a pick-up method only where the town has an active point of its kind, the courier only
in its towns.

A town a point, a method, a buyer's choice or an order points at is never deleted: «Delete»
switches it off, and it leaves the suggestions. When a carrier comes later, its sync binds its towns
to the ones made by hand — no second «Town A» — and never renames them; what it cannot place goes
to the binding queue.

## Try it with the demo

```bash
php artisan webx:demo --module=delivery
```

The country `XX` (a code for private use — no real country), «Region A» and «Region B» with six
towns made by hand, «Store A», «Store B» and two pick-up points, the methods «Pick up at a store»,
«Pick-up point» and «Our courier» (Town A and Town B, one price), and the popular towns Town A,
Town D, Town B. A site that already has delivery methods or towns in `XX` is left alone.
`webx:demo --remove --module=delivery` takes it all away again.

On a development site, `WEBX_DELIVERY_FAKE_CARRIER=true` in `.env` adds «Carrier A» — the fake
carrier of the tests, with branches, lockers and a courier by its own streets. The demo then switches
it on, runs its sync and makes its three methods: the checkout has a carrier to choose too, and the
panel's «Carriers» and «Binding queue» have something in them. Never switch it on in production:
its waybills go nowhere. Writing a carrier of your own — [Delivery carriers](./delivery-carriers.md).

## Put an order's delivery right

The buyer mistyped the street, chose the wrong pick-up point, or one the carrier will not take the
parcel to: on the order's tab «Delivery», **Change delivery** opens the checkout's choice again — the
town by the search and the popular towns, the methods of that town priced for the order's lines, the
point by its number or address, the address by the method's rules (a carrier's courier takes a
street of its list only). It is checked exactly as the checkout checks it.

«Check» shows what the delivery and the order's total were and will be. When the new method costs
another price, the total is counted again, as an edit of the lines counts it: a paid order shows
«Top-up due» or «Give back» in its «Summary». The same price, or «By carrier rates» on both sides —
the total stays. A line of the history says what the delivery was, what it is, why and who changed it.

Whoever edits orders (`orders.manage`) may change it, and not while a waybill stands: cancel the
waybill first — the carrier has the old address.

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
| `delivery_order_change`      | put the delivery of an order right — town, method, point or address; a dry run answers the new block and the difference of the sum |

The resource `delivery://catalog` lists the types of methods and what each can do, the kinds of
points, the carriers with their directories, the countries the site delivers to, what the
privilege `shipping.paid_by_store` does and the rules of the sync's removal with its threshold —
read from the registries, so a satellite shows up there once it is installed.

**A carrier's API key never passes through an agent**: no answer carries it and no tool takes it.
Keys are typed in the panel.

When a buyer says a method is missing in their town, start from `delivery_quote` with that town.

## Read more

- [Delivery carriers](./delivery-carriers.md) — writing a satellite of a carrier and its contract test.
- [Nova Poshta](./delivery-novaposhta.md) — the first carrier: connecting it, its directories and waybills.
- The delivery spec — [`WEBX_UI_MODULE_DELIVERY.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_DELIVERY.md).
- [AI agents](./agents.md) — connecting an agent, scopes and permissions.
