# Nova Poshta

`webx-ui/delivery-novaposhta` connects a shop on WebX UI to Nova Poshta, the national carrier of
Ukraine: its branches, parcel lockers and courier in the checkout, waybills from the order's tab
«Delivery» and the parcels' statuses in the order. It is a carrier satellite of
[Delivery](./delivery.md) — the towns, points and streets live in the site's own tables, and the
checkout never waits for the carrier's API.

## Install

```bash
composer require webx-ui/delivery-novaposhta
php artisan migrate
```

The migration adds the carrier `novaposhta` and three methods, all switched off: «Nova Poshta
branch», «Nova Poshta parcel locker» and «Nova Poshta courier». The country is `UA`; the delivery
must have it switched on in «Delivery → Geography».

## Connect the carrier

In the panel, «Delivery → Carriers → Nova Poshta»:

1. **Keys** — the API key from the shop's business account of the carrier (its settings, the
   section about the API). The key is stored encrypted and is never shown again: «Type a new one to
   replace». No agent ever reads or types it.
2. **Check the connection** — the panel asks the carrier for the account's senders (a reading
   request) and says «Senders in the account: 1», or why not: the key is not accepted, the carrier
   did not answer, the account has no sender.
3. **Settings — the sender.** Four lists, one after another, filled from the account and from the
   site's directories:
   - **Sender** — a counterparty of the account;
   - **Contact person** — one of the sender's;
   - **Town of sending** and **Branch of sending** — where the shop hands parcels over. Only a
     branch: a parcel locker takes no parcels from a shop.

   A sender or contact removed from the account later is marked «not in the carrier's account»
   and the waybill asks to choose another one.

4. **Settings — the waybill's defaults**: who pays for the delivery when the shop does (cash or
   non-cash under a contract), the cargo type, the weight, the description of the cargo and the
   size of a parcel-locker box. The manager can change each of them in the waybill's dialog.
5. Switch the carrier **On** and save.

## The directories

The carrier sends the towns, then its points — every night, page by page, about 11 000 towns and
50 000 branches and lockers — and the streets of the towns it drives a courier to once a week.
«Run» on a directory of the carrier's card starts it now; the card shows the runs, how many
records were seen and what waits in the binding queue.

- **Towns.** Nova Poshta becomes the leading carrier of `UA`: it makes the country's shared
  towns, and another carrier binds its own records to them. Towns of the same name in one region
  stay apart, told by the district from the carrier's name («Щасливе, Бориспільський р-н»); a
  town made by hand takes one record, never two.
- **Points.** Branches and the carrier's pick-up points become branches, parcel lockers become
  lockers; drop-off and warehouse points are skipped. A locker «for residents of the building»
  comes last in the list and says so. A town without coordinates gets the middle of its points.
- **Streets.** About 10 000 towns with a courier, one request each and more for a big city — the
  longest of the three. A courier method is offered only in a town whose streets have come.

Then switch the methods on in «Delivery → Methods» and put them in the checkout's order. Each of
them is priced «By carrier rates»: the buyer pays the carrier on receipt, or the shop pays when
the method says so.

## Waybills and tracking

On the order's tab «Delivery», **Create waybill** shows the request first: the recipient, the
value, the carrier's fields of the method — weight, seats, cargo type, the box of a parcel locker,
the date of sending. The recipient goes to the carrier as the order keeps them, first and last name
apart, in Ukrainian letters and with a Ukrainian mobile number; a name in other letters is marked
in the dialog before anything is sent. The carrier's refusals are said in words, with its own text
and codes. **Cancel waybill** cancels it at the carrier.

The base asks the carrier for the statuses of the parcels on its schedule, up to a hundred
parcels a request: «On the way», «At the pick-up point», «Received» — and «Received» marks the order
once. A key that is not your own tracks nothing (below).

## Somebody else's key

A copy of the site for development or a demo often runs on the key of a real shop. Set

```dotenv
NOVAPOSHTA_READ_ONLY=true
```

and the client refuses every method that changes the account — a waybill, a recipient, an address
— before a request is made. The directories and the sender's lists still work; «Create waybill»
and «Cancel waybill» say «This site uses the carrier's key read only: waybills are off.» The
tracking is off as well, the streets run by hand only and walk the first 20 towns — the account's
limits are somebody else's.

| Variable                         | What it does                                                               |
| -------------------------------- | -------------------------------------------------------------------------- |
| `NOVAPOSHTA_READ_ONLY`           | `true` — nothing is written to the account                                 |
| `NOVAPOSHTA_TRACKING`            | `false` — no tracking; empty — on, but off on a read-only key              |
| `NOVAPOSHTA_TIMEOUT`             | seconds to wait for the API, 10 by default                                 |
| `NOVAPOSHTA_REQUESTS_PER_MINUTE` | the pace of the directories' sync, 60 by default                           |
| `NOVAPOSHTA_STREETS_CRON`        | when the streets run: a cron or `manual`; weekly by default                |
| `NOVAPOSHTA_STREETS_SAMPLE`      | walk the streets of only this many towns; `0` — all; 20 on a read-only key |
| `NOVAPOSHTA_JOURNAL`             | the journal of calls — time, model, method, outcome, never the key or data |

The journal (`storage/logs/novaposhta-calls.log` by default) is how one checks which methods a
site called: a site on a read-only key has only `get…` methods there.

## Not in this version

- **The price from the carrier's API** — every method is «By carrier rates»; a fixed price of the
  method or «the shop pays» works as for any method.
- **Cash on delivery** and the payment control — the waybill's dialog has no such field.
- **Sending from the shop's address** — only from a branch of sending.
- **A printed form of the waybill** — print it in the carrier's account.
- **Waybills made by themselves** when an order is placed, and webhooks of the tracking.
- **Returns** — a parcel coming back is «Coming back», never «Returned to the shop».

## Read more

- [Delivery](./delivery.md) — methods, the shop's own points, the geography and the binding queue.
- [Delivery carriers](./delivery-carriers.md) — how a carrier satellite like this one is written.
- The spec — [`WEBX_UI_DELIVERY_NOVAPOSHTA.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_DELIVERY_NOVAPOSHTA.md).
