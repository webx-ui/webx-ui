# webx-ui/payment-liqpay

LiqPay for [WebX UI](https://github.com/webx-ui/webx-ui) payments (`webx-ui/module-payments`): the
gateway `liqpay` with its keys and settings, and one method type, `liqpay.checkout` — the hosted
page of LiqPay, with switches for what the page offers.

**Work in progress.** The package is built stage by stage: for now it registers the gateway, the
type and the method, but it does not take payments yet and «Test the connection» always fails.

```bash
composer require webx-ui/payment-liqpay
php artisan migrate
```

The migration adds the method `liqpay-checkout`, switched off. In the panel, «Payments → Methods →
LiqPay»: choose the mode, enter the keys of that mode, then switch the method on.

| Key                                                                    | Where it lives                                            |
| ---------------------------------------------------------------------- | --------------------------------------------------------- |
| Public keys                                                            | settings of the method, in the panel                      |
| Private keys                                                           | the method's encrypted column, or the site's `.env`       |
| `WEBX_PAYMENTS_LIQPAY_CHECKOUT_TEST_PRIVATE_KEY`, `…_LIVE_PRIVATE_KEY` | the `.env` names of the private keys of `liqpay-checkout` |

A second method of the type has keys and a notification address of its own.

Agents and contributors: read [`AGENTS.md`](AGENTS.md).

MIT licence.
