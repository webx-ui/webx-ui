# Payments

`webx-ui/module-payments` is how a shop on WebX UI takes the money for an order: which payment
methods a buyer is offered for this cart and this delivery, the step «Payment» of the checkout, the
details of a bank transfer on «Thank you», «Mark as paid» for a manager, and online payment through
a gateway. A gateway comes as a satellite package; a shop without one works with the two built-in
methods — on receipt and by bank transfer.

Everything the shop sends a gateway and everything a gateway sends back is kept whole in the
**exchange journal**: the raw body, the headers, the status, the time, the signature check and what
the shop decided — the evidence of any dispute about a payment.

## Install

```bash
composer require webx-ui/module-payments
php artisan migrate
```

It requires `webx-ui/module-orders` (and through it `webx-ui/module-cart`). The panel gets the
section «Payments» — methods, attempts and the gateway exchange — and the order's card gets the tab
«Payment». The checkout gets its step «Payment» by itself.

Online payment needs the scheduler: `webx:payments:poll` runs every five minutes and asks the
gateway about payments nobody told the shop about.

## A shop without a gateway

The migration adds two methods, both switched off. Switch them on in **Payments → Methods**:

- **On receipt** — the buyer pays at the shop's counter or the carrier's courier. The order does
  not await payment. By default it is offered only with a delivery where money changes hands — a
  pick-up at the shop's point or cash on delivery; at the shop's point the buyer reads «Pay at the
  counter».
- **Bank transfer** — the order awaits payment until a manager marks it paid. Fill in the
  recipient, the account and the bank (one field: the format is the country's), the purpose — `{number}`
  stands for the order's number — and a note, such as how many days the buyer has. Each in every
  language of the site. The buyer sees the details on «Thank you», on the order's page and in the
  letter.

Each method also has its rules: the amount it is offered from and up to, and the deliveries it goes
with — any, only where money changes hands, only the methods listed, or all but those. Why the
checkout does not offer a method to a cart, the agent's `payments_available` says.

### Mark as paid

The money came by transfer: on the order, **Mark as paid** in the header — the document's number,
the day the money came, a note. The order gets the milestone «paid», leaves «Awaiting payment», and
the tab «Payment» shows what paid it and who marked it. The sum is the order's: there is no partial
payment by transfer — a buyer who sent less is a phone call.

## Online payment

A gateway is a satellite package, `payment-<gateway>`: install it, open its method in **Payments →
Methods**, type the keys of the gateway's account and press **Test the connection**. Keys are
stored encrypted, shown as «set» and never sent back — not to the panel, not to an agent. Copy the
**notification address** the form shows into the gateway's account if the gateway asks for one; the
return address goes with every payment by itself.

What the buyer sees: the order is placed, the browser goes to the gateway's page, the buyer pays and
comes back to «Thank you». The shop believes nothing the browser brings back — the status comes from
the gateway's signed notification or from its API.

- **Notifications.** The gateway posts to `/payments/notify/{method}`. The raw request is in the
  journal before anything reads it; a bad signature changes nothing. A gateway whose notifications
  are not signed is never believed: its notification is only a reason for the shop to ask the
  gateway's API itself.
- **Pay again.** A payment that did not go through leaves the order awaiting payment; the buyer's
  page of the order and the letter carry a link «Pay» to a new attempt, and the buyer may switch to
  another method there.
- **Problems.** Money for a cancelled order, a second payment for a paid one, a sum that does not
  match — the attempt is marked a problem in **Payments → Attempts → Problems**; a manager settles
  it with the gateway and presses «Resolved».

### Hold and capture

A gateway that can hold money (a card blocked, not charged) offers it as the method's switch «Hold
the money, capture later». The order then shows «Money held» until a manager decides, on the tab
«Payment»:

- **Capture** — all of the money or a part. A capture that covers the order pays it. Less than the
  order — the items were lowered first, or not — is money in without the milestone «paid»: the order
  owes the rest, its attempt reads «Captured 50,00 € of 92,90 €».
- **Release the hold** — the money goes back to the buyer; the order awaits payment as before.

Both show what they will do before they do it. A hold the gateway lets expire is a failed payment;
the poll warns before.

## The exchange journal

**Payments → Gateway exchange** lists every event: what went out to the gateway, what came in, what
the buyer's browser brought, and the shop's own decisions. Filters by gateway, method, kind,
direction, the order's number or the attempt; «Signature errors» and «Errors only» at a click. An
event opens with its request, its answer and the whole trace it belongs to.

- The headers and bodies are behind their own permission, `payments.exchange` — by default only the
  super-admin has it. Without it the list and the decisions are visible, the bodies are not.
- The keys of the gateway are masked wherever they stand — as they are, url-encoded, in JSON, in
  base64 — and so are `Authorization`, cookies and card numbers. A signature is kept: it is the
  evidence.
- A body is kept whole up to 1 MB; a longer one is cut, with its full size and sha256.
- Nothing removes an event by itself, and nobody edits one. A journal older than a year may be
  pruned by hand:

```bash
php artisan webx:payments:exchange-prune --days=365 --dry-run
php artisan webx:payments:exchange-prune --days=365
```

`webx:doctor` notes a journal over 1 GB. `WEBX_PAYMENTS_EXCHANGE_CONNECTION` moves it to another
database; empty, it writes through a copy of the default connection, so that a rolled-back status
change never takes its event with it.

## Permissions

| Permission          | Opens                                                                                      |
| ------------------- | ------------------------------------------------------------------------------------------ |
| `payments.view`     | methods without keys, attempts, the exchange without bodies, the tab «Payment» of an order |
| `payments.manage`   | the methods: on and off, order, words, rules, limits, settings, keys and their test        |
| `payments.exchange` | raw headers and bodies of the exchange; only the super-admin by default                    |

What is done to an order — mark paid, check with the gateway, capture or release, change the method,
the payment link, a problem resolved — needs `orders.manage`.

## Try it with the demo

```bash
php artisan webx:demo --module=payments
```

Both built-in methods switched on, the transfer with fake details («Shop A Ltd», an account of
zeros at «Bank A»). `webx:demo --remove --module=payments` switches them off again.

On a development site, `WEBX_PAYMENTS_FAKE_GATEWAY=true` in `.env` adds **«gateway A»** — the fake
gateway of the tests — and the demo switches its three methods on with its test key: «Card (gateway
A)», which can hold money, «Gateway A page», which approves first, and «Gateway A (unsigned)», whose
notifications are not believed. Its «page» at `/pay/fake/{uuid}` has a button for each thing a
gateway does: pay, decline, cancel, pay with the notification later, the notification again, a bad
signature, a payment of nobody's, hold, approve. Each lands in the journal as a real gateway's would.
Never in production: off there whatever `.env` says, and its payments move no money.

## Agents

With the panel and `webx-ui/mcp` installed, an agent works through the same doors as the panel.
Reading tools are behind `payments:read` and `payments.view`, writing ones behind `payments:write`;
what is done to an order needs `orders.manage`. Every write takes `dry_run`.

| Tool                       | What it does                                                                        |
| -------------------------- | ----------------------------------------------------------------------------------- |
| `payments_methods_list`    | methods in the checkout's order: type, titles, rules, limits, keys only as «set»    |
| `payments_methods_save`    | create a method of a type, or change one; never its keys                            |
| `payments_methods_reorder` | the checkout's order of methods                                                     |
| `payments_available`       | the methods of a cart or an amount and a delivery, each with why it is not offered  |
| `payments_list`            | attempts by order, method, status, problem                                          |
| `payments_get`             | an attempt with its events                                                          |
| `payments_check`           | ask the gateway about an attempt in progress or held                                |
| `payments_capture`         | capture a hold, all or a part; a dry run says whether it pays the order             |
| `payments_void`            | release a hold                                                                      |
| `payments_mark_paid`       | mark an order paid by transfer: document, day, note                                 |
| `payments_switch_method`   | another method for an order awaiting payment                                        |
| `payments_pay_link`        | the signed link to the order's page of payment                                      |
| `payments_problems`        | attempts with problems, open or resolved                                            |
| `payments_resolve_problem` | mark a problem settled, with a note                                                 |
| `payments_exchange_list`   | events of the journal by method, kind, decision, order, payment — no bodies         |
| `payments_exchange_get`    | one event or a trace with its headers and bodies (up to 64 KB), `payments.exchange` |

The resource `payments://catalog` lists the types of methods and what each can do, the gateways
installed and the delivery rules. **A gateway's key never passes through an agent**: no answer
carries it and no tool takes it.

## Letters

With `webx-ui/module-notifications`, the letters of orders get `payment_url` (the link «Pay»),
`payment_instructions` (the transfer's details) and `payment_method`. The event `payments.problem`
tells the managers about a problem; its channels are the setting «Payments» of the site's settings.

## Read more

- [Payment gateways](./payment-gateways.md) — writing a satellite of a gateway and its contract test.
- [Orders](./orders.md) — milestones, the balance, top-ups.
- [Checkout](./checkout.md) — the steps and the slots.
- The payments spec — [`WEBX_UI_MODULE_PAYMENTS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_PAYMENTS.md).
- [AI agents](./agents.md) — connecting an agent, scopes and permissions.
