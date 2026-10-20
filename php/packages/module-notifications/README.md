# webx-ui/module-notifications

Notification channels for the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel. An
administrator sets up a channel once — a Telegram bot and its chats, or e-mail and its addresses,
with a message template — and modules that have something to say (a form's new submission, an
order) send to the channels they were given.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-auth`, `webx-ui/localization`
- a queue worker for retries in the background (without one, messages leave after the response)

## Install

```bash
composer require webx-ui/module-notifications
php artisan migrate
```

The section "Notifications" is behind two permissions, `notifications.view` and
`notifications.manage`, which no role gets on install: only a super administrator sees it until
somebody grants them.

## Channels

- **Telegram** — a bot token and chat ids; "Find chats" reads the bot's recent updates.
  Templates are Telegram's HTML; the values of variables are escaped.
- **E-mail** — the site's mail or an SMTP server of the channel's own; the body is text, sent as
  text and as HTML in a quiet frame.

Secrets are encrypted with the application key and never answered: the panel only learns that a
secret is set, and the journal writes its change as a mask.

## Sending from a module

Declare the event in your provider without requiring this package:

```php
use Illuminate\Support\Facades\Lang;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;
use WebxUi\Notifications\NotificationEvents;

if (class_exists(NotificationEvents::class)) {
    $this->callAfterResolving(NotificationEvents::class, function (NotificationEvents $events): void {
        $events->register(new EventDefinition(
            key: 'shop.order',
            label: 'shop::notifications.order',
            module: 'shop',
            variables: [
                new Variable('number', 'shop::notifications.number', example: '1024'),
                new Variable('customer_email', 'shop::notifications.email', example: 'customer@example.com', address: 'email'),
            ],
            // The default template, by provider (`*` — any other): one text, or a function of the
            // language answering null where the module has no words. A new channel starts with it.
            sample: [
                'email' => fn (string $locale): ?array => Lang::hasForLocale('shop::notifications.order', $locale)
                    ? ['subject' => 'Order {{number}}', 'template' => '<p>'.e(trans('shop::notifications.order', [], $locale)).' {{number}}</p>']
                    : null,
                '*' => ['subject' => 'Order {{number}}', 'template' => 'Order {{number}} is placed.'],
            ],
        ));
    });
}
```

Then send:

```php
app(\WebxUi\Notifications\Notifications::class)->send(
    event: 'shop.order',
    channels: $settings->orderChannels(),
    variables: ['number' => $order->number, 'customer_email' => $order->email],
    subject: $order,
    locale: $order->locale,
);
```

Every recipient gets a row in the delivery log at once; one queued job per channel makes three
attempts. `send()` never throws.

## Commands

- `php artisan webx:notifications:prune {--days=} {--dry-run}` — trims the delivery log (90 days by
  default, scheduled daily).
- `php artisan webx:doctor` — says what waits for a worker, what failed, and which channels cannot
  send.

## License

MIT
