# webx-ui/payment-liqpay

LiqPay for [WebX UI](https://github.com/webx-ui/webx-ui) payments (`webx-ui/module-payments`): the
gateway `liqpay` with its keys and settings, and one method type, `liqpay.checkout` — the hosted
page of LiqPay, with switches for what the page offers and a hold for card payments.

```bash
composer require webx-ui/payment-liqpay
php artisan migrate
```

The migration adds the method `liqpay-checkout`, switched off. In the panel, «Payments → Methods →
LiqPay»: choose the mode, enter the keys of that mode, save, test the connection, then offer the
method in the checkout.

| Key                                                                    | Where it lives                                            |
| ---------------------------------------------------------------------- | --------------------------------------------------------- |
| Public keys                                                            | settings of the method, in the panel                      |
| Private keys                                                           | the method's encrypted column, or the site's `.env`       |
| `WEBX_PAYMENTS_LIQPAY_CHECKOUT_TEST_PRIVATE_KEY`, `…_LIVE_PRIVATE_KEY` | the `.env` names of the private keys of `liqpay-checkout` |

A second method of the type has switches, keys and a notification address of its own: that is how a
shop offers the card and the instalments as two lines of the checkout.

- **Currencies:** UAH, USD, EUR. A part of a hold can be captured in hryvnias only.
- **Refunds** are made in the LiqPay cabinet. Fiscal receipts and the widget are not in this version.
- **Notifications** are built from the documentation and from recorded answers of the sandbox, and
  have not been checked against a live one yet. The poll closes what a notification misses.

The guide: [LiqPay](https://github.com/webx-ui/webx-ui/blob/main/apps/docs/guide/payment-liqpay.md).
Agents and contributors: read [`AGENTS.md`](AGENTS.md).

MIT licence.
