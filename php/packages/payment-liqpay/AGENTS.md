# webx-ui/payment-liqpay

LiqPay, a payment gateway of Ukraine, for `webx-ui/module-payments`. The package adds the gateway
`liqpay` and one method type, `liqpay.checkout`. The base owns everything else: the buyer's pages,
`settle()`, the exchange journal, the poll, the method form and MCP. Read the base's `AGENTS.md`
first. This package has no tables, routes, screens, MCP tools or npm package of its own.

**Being built by stages** (§19 of the spec). There now: the registration, the fields, the words, the
migration, the signature, the client and `readNotification()`. `start()`, `fetchStatus()` and
`testConnection()` refuse until their stages land, and the type is not `CapturesPayments` yet, so
the hold switch is not drawn. A `reversed` notification asks `fetchStatus()`, so it answers 500 until then.

## What it owns

- **Gateway** `LiqPayGateway`, key `liqpay`. Settings: `mode` (`test` / `live`), `test_public_key`,
  `live_public_key`, `language` (`auto` / `uk` / `en`) and `expires_minutes` (60). Credentials:
  `test_private_key` and `live_private_key`. No secret headers or fields. `notCardFields()` lists
  the dates in milliseconds and the payment numbers, which pass Luhn by chance.
- **Public key in the settings, on purpose.** It travels inside `data`, and the journal wipes
  credentials even inside base64: as a credential it would break the string the signature covers.
- **Type** `LiqPayCheckoutType`, key `liqpay.checkout`: online, currencies `UAH`, `USD`, `EUR`. Seven
  switches `pay_*` choose the values of `paytypes`. A method has its own switches, keys and
  notification address, so a second method needs its keys entered again.
- **Hold and instalments never share a method.** `pay_moment_part` and `pay_paypart` carry `off_with:
hold`, and `validateSettings()` refuses the pair on the server. It also refuses a hold with no
  switch on: without `paytypes` the page shows everything, instalments too.
- **Signature** `Api\Signature`: `version` decides the algorithm of our requests, and a notification
  passes with SHA3-256 or SHA1 until a live one shows which LiqPay uses. It covers the string `data`
  as it came, never the decoded JSON. Only the key of the method's mode counts (`Keys`).
- **Statuses** `Api\Statuses`: the table of §9.2. `reversed` and `refund_amount` are a poke, because
  their meaning depends on the attempt. `try_again` is Pending; `wait_accept` is Paid with a reason.
- **Fixtures** `tests/Fixtures`: `api/` and `browser/` were recorded on the sandbox, `notify/` is
  emulated. `FixturesAreScrubbedTest` keeps real keys, cards and phones out.
- **Migration**: the method `liqpay-checkout`, switched off, no title of its own. A second run adds
  nothing. The rollback removes it only while it has no attempts.
- **Config** `webx-payment-liqpay.php`: the two addresses and the version of the API.
- **Words**: `webx-payment-liqpay::liqpay.*` in 10 languages (`gateway`, `paytypes`, `connection`,
  `status`, `errors`).

### Permissions

None of its own. The method, its keys and «Test the connection» are behind the base's permissions.

## Change it without forking

| You want                         | Do this                                                         |
| -------------------------------- | --------------------------------------------------------------- |
| The private key out of the base  | `WEBX_PAYMENTS_<METHOD>_TEST_PRIVATE_KEY`, `…_LIVE_PRIVATE_KEY` |
| Two ways to pay with other pages | add a second method of the type in «Payments → Methods»         |
| Another address (a test server)  | `LIQPAY_API_URL`, `LIQPAY_CHECKOUT_URL`                         |
| Other words                      | `php artisan vendor:publish --tag=webx-payment-liqpay-lang`     |
| The config in the site           | `php artisan vendor:publish --tag=webx-payment-liqpay-config`   |

## Do not

- Do not move the public key to `credentials()`: the journal stops being able to check a signature.
- Do not change `version` alone. It decides the algorithm: `7` is SHA3-256, `3` to `6` are SHA1, and
  any other pair is refused.
- Do not add a call that sends card fields through the API. A buyer enters a card only on the page
  of LiqPay; that way of paying needs PCI DSS and belongs to recording answers, outside the package.
- Do not put a real key in a test, a fixture or a log, not even a sandbox one.
- Do not offer `cash` or `invoice` in `paytypes`: their statuses keep an attempt open.

## Check your work

- `vendor/bin/phpunit packages/payment-liqpay/tests` from `php/` of the monorepo.
- «Payments → Methods» in the panel shows LiqPay, switched off, with a warning that it has no keys.
- `php artisan migrate:rollback` removes a method without attempts and leaves one that has them.

## Read more

- The satellite's spec: `docs/architecture/WEBX_UI_PAYMENT_LIQPAY.md` in the monorepo.
- The base: `webx-ui/module-payments`, its `AGENTS.md` and the guide «Payment gateways»
  (`apps/docs/guide/payment-gateways.md`).
