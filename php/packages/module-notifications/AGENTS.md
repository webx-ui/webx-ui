# webx-ui/module-notifications

Notification channels for every module of the site. An administrator sets up a **channel** once —
a provider (Telegram, e-mail, or one a package adds), its secrets, recipients and a Mustache
template — and modules that **declare an event** send to the channels they keep a list of. The
first consumer is `webx-ui/module-inbox` (event `inbox.submission`). The section «Notifications»
of the panel and the MCP tools `notifications_*` edit channels; the panel and its journal are
`webx-ui/module-admin` — read its guide for those.

## What it owns

- **Tables** `notifications_channels` (`Models\Channel`: `name`, `provider`, `event` — null for a
  channel of any event, `credentials` — `encrypted:array`, hidden, `options`, `recipients`,
  `subject` and `template` — one text per language, `enabled`, `copied_from`; soft deletes) and
  `notifications_deliveries` (`Models\Delivery`: one row per recipient — `queued`, `sent`,
  `failed`, `skipped` with a `reason` — with the values the event brought).
- **Mail that goes nowhere**: `EmailProvider` is `Contracts\HasMailRoute`, described by the frame's `Mail\MailRoutes`; a letter a
  trap took (`log`, `array`, a sandbox) is `sent` with `trap`; `mail` and `warnings` in the panel, `notifications_channel_get`, `_test`, `_deliveries`.
- **Registries** `Providers` (`telegram`, `email`) and `NotificationEvents`.
- **API for consumers** `Notifications::send()`; the job `Jobs\Deliver` — three attempts, after
  the response when the queue is `sync`; `Notifications::test()`, `retry()`, `resend()` and `sendByHand()` (by hand, §8.6), `deliveriesFor()`.
- **Panel screen** `notifications.channel`, API under `/api/cms/notifications`.
- **MCP** tools `notifications_channels_list`, `_channel_get`, `_channel_create`, `_channel_update`,
  `_channel_delete`, `_channel_copy`, `_default_template`, `_preview`, `_test`, `_find_chats`,
  `_deliveries`, `_retry`;
  resources `notifications://providers`, `notifications://events`, `notifications://channels`.
  Scopes `notifications:read`, `notifications:write`.
- **Command** `webx:notifications:prune {--days=} {--dry-run}`, scheduled daily; the doctor check
  `Doctor\NotificationsCheck`. Config `config/webx-notifications.php`.
- Also registered: demo content (`resources/demo`) — one e-mail channel, switched off.

### Permissions

| Permission             | Opens                                                                                                                                                                                                                                                                             |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `notifications.view`   | channels and the delivery log, read-only — with what visitors typed (`GET meta`, `channels`, `channels/{channel}`, `deliveries`); `notifications_channels_list`, `_channel_get`, `_preview`, `_deliveries`; the journal of a channel                                              |
| `notifications.manage` | the same, plus create, edit, copy, delete, restore and purge channels, test and preview sends, find chats and recipients, the administrators picker, retry a delivery; `_channel_create`, `_channel_update`, `_channel_delete`, `_channel_copy`, `_test`, `_find_chats`, `_retry` |

Ready-made roles: `leads` holds `notifications.view`, `developer` both. A route or screen node the
site adds next to the module asks the same: `cms.can:notifications.view,notifications.manage` to
read, `cms.can:notifications.manage` to write.

## Change it without forking

| You want                                    | Do this                                                           |
| ------------------------------------------- | ----------------------------------------------------------------- |
| Your module to notify about something       | declare an event and call `send()` (below)                        |
| A provider of your own (Slack, SMS, a hook) | implement `Contracts\Provider` and register it (below)            |
| A provider that finds its recipients        | also implement `Contracts\FindsRecipients`                        |
| Notifications sent without a queue worker   | `WEBX_NOTIFICATIONS_QUEUE_MODE=after_response`                    |
| Another queue or connection                 | `WEBX_NOTIFICATIONS_QUEUE`, `WEBX_NOTIFICATIONS_QUEUE_CONNECTION` |
| The log kept longer or forever              | `WEBX_NOTIFICATIONS_LOG_DAYS` (0 keeps it)                        |
| Other words in the panel                    | `php artisan vendor:publish --tag=webx-notifications-lang`        |
| The config file in the site                 | `php artisan vendor:publish --tag=webx-notifications-config`      |

An event — variables with examples, a list with the fields of its rows, an addressee — declared
from your provider without requiring this package:

```php
if (class_exists(NotificationEvents::class)) {
    $this->callAfterResolving(NotificationEvents::class, fn (NotificationEvents $events) => $events->register(new EventDefinition(
        key: 'shop.order', label: 'shop::notifications.event', module: 'shop',
        variables: [
            new Variable('number', 'shop::notifications.vars.number', example: '1024'),
            new Variable('items', 'shop::notifications.vars.items', example: [['name' => 'Tea', 'qty' => '2']],
                fields: ['name' => 'shop::notifications.vars.name', 'qty' => 'shop::notifications.vars.qty']),
            new Variable('customer_email', 'shop::notifications.vars.email', example: 'customer@example.com', address: 'email'),
        ],
        sample: [
            'email' => fn (string $locale): ?array => Lang::hasForLocale('shop::notifications.order', $locale)
                ? ['subject' => 'Order {{number}}', 'template' => '<p>'.e(trans('shop::notifications.order', [], $locale)).'</p>'] : null,
            '*' => ['subject' => 'Order {{number}}', 'template' => "Order {{number}}\n{{#items}}- {{name}} × {{qty}}\n{{/items}}"],
        ],
        url: fn (array $values) => url('/cms/orders/'.($values['number'] ?? '')),
    )));
}
```

Send it — the values as they are now, the channel ids the consumer keeps (in its own data), the
record it is about and the language it was made in. `send()` never throws: a channel that cannot
take it is a `skipped` row with the reason.

```php
app(Notifications::class)->send('shop.order', $order->notify_channels, [
    'number' => $order->number, 'items' => $rows, 'customer_email' => $order->email,
], subject: $order, locale: $order->locale);
```

`sample` is the event's **default template** by provider (`*` — any other): one text for every
language, or a function of the language that answers null where the module has no words. A new
channel starts with it in each language it has (the main one always); `default_template: true`
puts it back. It passes the same check as a typed one — test it with `notifications_default_template`.

A consumer as it is: `webx-ui/module-inbox`, `src/Notifications` — the event (`SubmissionEvent`), a
listener of its own Laravel event calling `send()` (`SendToChannels`), and `Channels`: whether this
module is there (`bound(NotificationEvents::class)`), the ids a form keeps, `deliveriesFor()`, `retry()`.

A channel writes to an addressee with the recipient `{"variable": "customer_email"}` — only on a
channel of that event, only of the provider's kind; one using an `internal: true` variable warns.

**Templates are Mustache** (`mustache/mustache`): `{{name}}`, `{{field.phone}}` (one dot),
`{{#items}}…{{/items}}` over a list — inside, a name is a field of the row first, then a variable
of the event — and `{{#name}}…{{/name}}` / `{{^name}}…{{/name}}` for there or empty. Values are
escaped by the provider; `{{{name}}}` and `{{&name}}` are the same as `{{name}}`. Partials,
delimiter changes, pragmas, blocks and names in capitals are refused on save, with words that say
what to fix. A channel of an event refuses names the event does not have; one for any event warns.
A letter's template that starts with a tag is HTML (`Email\EmailMarkup`: table tags and inline
styles; values escaped, line breaks kept, the plain part read out of it); otherwise it is text.

A provider: `key()`, `fields()` (`WebxUi\Admin\Support\Field`, `secret: true` goes to `credentials`),
`recipients()` to check and shape them, `resolve()` to turn them into `Recipient`s at sending time,
`escape()`, `checkTemplate()`, `send()` throwing `DeliveryFailed` (`permanent` or `retryAfter`),
`check()`, and `screen()` — the form's nodes, so it shows in the panel without Vue. Register it:
`$this->callAfterResolving(Providers::class, fn (Providers $p) => $p->register($app->make(Slack::class)));`

## Do not

- Do not edit anything in `vendor/webx-ui/module-notifications` or copy it into the site. The rows
  above are the supported ways; if none fits, the package is missing a seam — say so.
- Do not render a template with Blade or `eval`, or let a template come from a visitor: the
  template is the administrator's, the values are the visitor's and are escaped.
- Do not read `credentials` to show or log them, and do not put a secret into `options`: a field
  of a provider that is a key, a password or a token is `secret: true`.
- Do not call a provider's `send()` from a consumer: go through `Notifications::send()`, or the
  log, the retries and the switched-off channel are skipped.
- Do not require this package from a consumer — declare the event under `class_exists`.
- Do not enable a channel or call `notifications_test` from MCP unless a person asked; never
  invent tokens, chat ids or addresses — ask for them. Demo and test data use `example.com`.
- Do not write channels with SQL: the history of changes and the secrets' encryption are the
  model's.

## Check your work

- `php artisan webx:mcp-tools --module=notifications` — the twelve tools are there.
- `php artisan webx:doctor` — the queue without a worker, deliveries stuck or failed lately,
  channels whose provider or event is gone, secrets that no longer decrypt, mail that goes nowhere.
- With MCP: read `notifications://events` and `notifications://providers` first; try a template with
  `notifications_preview`, every write with `dry_run: true`; then a test, and
  `notifications_deliveries` to see what went and why not.
- A consumer's event: it is in `notifications://events`, its sample saves as a channel, and a
  `send()` in a test makes one `queued` row per recipient (`Bus::fake()` keeps the job).

## Read more

- [README.md](README.md) in this directory — installing, channels, sending from a module, commands.
- Guide: https://webx-ui.github.io/webx-ui/guide/notifications — channels, templates, the editor.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_NOTIFICATIONS.md
- MCP access and scopes: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MCP_ACCESS.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
