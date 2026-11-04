# webx-ui/payment-liqpay

LiqPay, a payment gateway of Ukraine, for `webx-ui/module-payments`. The package adds the gateway
`liqpay` and one method type, `liqpay.checkout`. The base owns everything else: the buyer's pages,
`settle()`, the exchange journal, the poll, the method form and MCP. Read the base's `AGENTS.md`
first. This package has no tables, routes, screens, MCP tools or npm package of its own.

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
- **The page** `start()`: signs the form and asks LiqPay nothing, so no `out/start` and no `gatewayRef`.
  `order_id` is our uuid of the attempt. `expired_date` goes always, 60 minutes when the method is silent.
- **Statuses** `Api\Statuses`: the table of §9.2. In a notification `reversed` and `refund_amount` are a
  poke, because their meaning depends on the attempt: `fetchStatus()` sees it, `Cancelled` at
  `authorized` and `Refunded` elsewhere. `try_again` is Pending with `declined`, `wait_accept` is Paid with a
  reason, and an answer without `payment_id` is a failed request, not a payment. `payment_not_found`
  is Pending with `notStarted`: the buyer has not sent a card, a late `success` still counts.
- **Hold** `CapturesPayments`: the base's switch `hold` sends `action=hold` to the page. `capture()` is
  `hold_completion`, whole or a part: LiqPay lets the rest go itself. `payment_err_status` asks `status`,
  because a lost answer and a hold that is gone say the same. `void()` is `refund` of all that is held,
  only at `authorized`. LiqPay names no end of a hold: `create_date` + 10 days for Visa, + 7 for the rest.
- **A part needs hryvnias.** A hold in EUR or USD is debited in UAH, and `hold_completion` of a part answers
  `wrong_amount_currency` whatever the request says. The whole sum and a release work in any currency.
  `capturesPartially()` knows no payment, so the manager gets a refusal in words and the hold stays.
- **Test the connection** `testConnection()`: `status` of a random uuid, `payment_not_found` means both
  keys are right. Keys of the other mode (`sandbox_` prefix) are refused without a request. The public
  key comes from the saved method: save the form first, or the answer is «Enter both keys of this mode».
- **MCP**: only the base's `payments_*`. A private key is refused there and answers say «set». The
  public key is a setting, so an agent sees it and `payments_methods_save` takes it.
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
- Do not call `refund` outside `void()`: at a paid attempt it gives the money back (not in v1).
- Do not put a real key in a test, a fixture or a log, not even a sandbox one.
- Do not offer `cash` or `invoice` in `paytypes`: their statuses keep an attempt open.

## Check your work

- `vendor/bin/phpunit packages/payment-liqpay/tests` from `php/` of the monorepo, in a shell without
  `WEBX_PAYMENTS_LIQPAY_*`: a variable from the lab's `.lab-env` beats what the tests set up.
- «Payments → Methods» in the panel shows LiqPay, switched off, with a warning that it has no keys.
- With sandbox keys «Test the connection» succeeds, and a test card on the page ends paid by the poll.
- With `hold` on, a test card ends held; «Capture» and «Release the hold» in the order's card end it.
- `php artisan migrate:rollback` removes a method without attempts and leaves one that has them.

## Read more

- The satellite's spec: `docs/architecture/WEBX_UI_PAYMENT_LIQPAY.md` in the monorepo.
- The guide for people: `apps/docs/guide/payment-liqpay.md`.
- The base: `webx-ui/module-payments`, its `AGENTS.md` and the guide «Payment gateways»
  (`apps/docs/guide/payment-gateways.md`).
