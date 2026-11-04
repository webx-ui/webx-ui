# LiqPay

`webx-ui/payment-liqpay` connects a shop on WebX UI to LiqPay, a payment gateway of Ukraine: the
buyer pays on the hosted page of LiqPay, by card, Apple Pay, Google Pay, Privat24, a QR code or in
instalments, and the order is paid when LiqPay says so. It is a satellite of
[Payments](./payments.md): the pay page, the statuses of an attempt, the exchange journal, the hold's
buttons and the method's form are the base's. The package adds the gateway `liqpay` and one type of
method, `liqpay.checkout`, and has no section of its own in the panel.

A buyer enters a card only on the page of LiqPay. The site never sees it.

## Install

```bash
composer require webx-ui/payment-liqpay
php artisan migrate
```

The migration adds the method `liqpay-checkout`, switched off. It takes hryvnias, dollars and euros:
in a shop with another currency the checkout does not offer it.

## Connect the keys

A LiqPay shop has a pair of keys for each mode, in its settings in the LiqPay cabinet: a public one
and a private one. The sandbox pair starts with `sandbox_`.

In the panel, «Payments → Methods → LiqPay»:

1. **Mode** — start with Test.
2. **Public key** of that mode. It goes to the buyer's browser with every payment, so it is a plain
   setting and you can read it in the form.
3. **Private key** of that mode, under «Gateway keys». It is stored encrypted and is never shown
   again: the form says «Set», with «Replace» and «Remove key».
4. **Save.** «Test the connection» reads the public key from the saved method, so an unsaved one
   ends in «Enter both keys of this mode».
5. **Test the connection.** The panel asks LiqPay about a payment nobody made and says «LiqPay
   accepted the keys», or which key is wrong. Keys of the other mode are refused before anything is
   sent: sandbox keys in Live mode, or live keys in Test mode, would mean money where nobody expects
   it, or none where they do.
6. Switch on **Offered in the checkout** and save.

The block «For the gateway account» shows the notifications address. There is nothing to copy for
LiqPay: the address goes with every payment.

The private key can live in the site's `.env` instead. The name comes from the method's key:

```ini
WEBX_PAYMENTS_LIQPAY_CHECKOUT_TEST_PRIVATE_KEY=
WEBX_PAYMENTS_LIQPAY_CHECKOUT_LIVE_PRIVATE_KEY=
```

A key in the environment wins, and the form stops offering that field. The public key has no such
variable: it is entered only in the form.

## Test mode

In Test mode, with the sandbox keys, the page takes the
[test cards of LiqPay](https://www.liqpay.ua/uk/doc/api/testing) and no money moves. Any future date
and any three digits will do.

| Card                  | What happens                                     |
| --------------------- | ------------------------------------------------ |
| `4242 4242 4242 4242` | paid                                             |
| `4000 0000 0000 0002` | declined; the page offers to try again           |
| `4000 0000 0000 3063` | paid through 3-D Secure, passed without a window |

The page does not bring the buyer back by itself: they press «Повернутися на сайт». A closed tab is
an ordinary case, the site learns the status from the notification or from the poll.

The notification needs a public address. On a development site without one the status comes when
the buyer returns, and from the poll.

Moving to live: choose Live, enter the live pair, save, test the connection. Each mode keeps its own
pair, so going back to Test needs no typing.

## What the page offers

The switches of the method choose what LiqPay shows, in this order:

| Switch              | On by default | Note                                  |
| ------------------- | ------------- | ------------------------------------- |
| Card                | yes           |                                       |
| Apple Pay           | yes           |                                       |
| Google Pay          | yes           |                                       |
| Privat24            | no            |                                       |
| QR code             | no            |                                       |
| Instant instalments | no            | the service must be on in the cabinet |
| Pay in parts        | no            | the service must be on in the cabinet |

- **Every switch off** — the page shows all that is switched on in the cabinet.
- **A way the shop does not have gives no error**: LiqPay drops it, and a method with nothing else
  left shows the whole page. So check a method with one switch on the page itself.
- With instalments too the shop receives the whole sum at once. The base notices nothing.

Language of the page: LiqPay speaks Ukrainian and English only. «By the language of the order» gives
Ukrainian to an order in Ukrainian and English to the rest.

«Minutes the payment page works» is 60 unless you say otherwise. After that the page still opens,
but it refuses the card, and the attempt ends as expired.

## Two methods with different switches

A method is one line in the checkout. To offer the card and the instalments as two lines, add a
second method of the type: «Add method» → LiqPay.

| Method              | Title for the buyer | Switches                    |
| ------------------- | ------------------- | --------------------------- |
| `liqpay-checkout`   | Pay by card         | Card, Apple Pay, Google Pay |
| `liqpay-checkout-2` | Instant instalments | Instant instalments only    |

Each method has its own title, limits of the amount, rule of delivery and place in the checkout.
It also has **its own keys**: enter them again in the second method, even when they are the same.
In `.env` its names are `WEBX_PAYMENTS_LIQPAY_CHECKOUT_2_TEST_PRIVATE_KEY` and so on.

## Hold

With **Hold the money, take it later** on, the bank blocks the amount on the card and the order is
paid when the manager takes the money. «Capture» and «Release the hold» are in the tab «Payment» of
the order; see [Hold and capture](./payments.md#hold-and-capture).

- **Card payments only.** With the hold on, the instalment switches are switched off and cannot be
  touched, and the server refuses the pair from an agent too. Need both? Make two methods.
- **A hold needs a choice.** A hold with every switch off is refused: the page would show the
  instalments as well.
- **How long.** LiqPay does not say. The site counts 10 days for Visa and 7 for other cards from the
  day of the payment, and warns the manager a day before the end.
- **A part only in hryvnias.** LiqPay captures a part of a hold in UAH. A hold in EUR or USD is
  debited in hryvnias, and a part of it is refused: the manager is told to capture the whole sum or
  to release the hold, and the hold stays. The whole sum and a release work in any currency.
- **What is left of a part** LiqPay lets go by itself.

## What the manager sees

Everything is in the journal, «Payments → Gateway exchange»: the form as it left for the browser,
each request of a status, each notification, with the decision of the base. The private key is never
there: only a hash made with it travels.

| At LiqPay          | In the shop                                                |
| ------------------ | ---------------------------------------------------------- |
| `success`          | paid                                                       |
| `wait_accept`      | paid; the journal says LiqPay has not checked the shop yet |
| `hold_wait`        | held                                                       |
| `failure`, `error` | not paid, with the reason in words                         |
| `try_again`        | still in progress, see below                               |
| `reversed`         | a hold released, or the money returned                     |
| no such payment    | the buyer has not sent a card: «Payment not completed»     |

- **The buyer cancelled on the page or closed it.** LiqPay has no record of that payment, so the
  buyer sees «Payment not completed» with «Pay again» and «Choose another method». The attempt stays
  open until its term, and a late `success` still counts.
- **The bank declined and the page offers another try.** The attempt stays in progress until its
  term: a buyer who returned reads «We are checking the payment» and has no button to pay. A try
  with another card on the same page pays the same attempt.
- **Two tabs.** LiqPay cannot close a page from outside. A new attempt warns about the open one, and
  a double payment shows up as the problem `duplicate`.

## Refunds and what is not there

A refund is made in the LiqPay cabinet: there is no button for it in the panel. The notification of
it reaches the site and the attempt becomes refunded.

Not in this version: fiscal receipts (`rro_info`), the `LiqPayCheckout` widget on the site,
subscriptions, invoices and cash.

## Agents

The package brings no tools of its own: an agent works with the method through the base's
`payments_*`. `payments_methods_save` changes the switches and the settings, and a hold with
instalments is refused there as in the panel. **A private key never passes through an agent**: the
answers say «set» or «missing», and a tool that is given one refuses it. The public key is a setting
like any other and is visible there.

## Read more

- [Payments](./payments.md) — methods, attempts, the journal, the hold.
- [Payment gateways](./payment-gateways.md) — how a satellite is built.
- The spec — [`WEBX_UI_PAYMENT_LIQPAY.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_PAYMENT_LIQPAY.md).
