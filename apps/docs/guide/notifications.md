# Notifications

::: v-pre
`@webx-ui/module-notifications` is the **Notifications** section of the panel, and
`webx-ui/module-notifications` on the server is what it edits and what sends. One mechanism for
every module: an administrator sets up a **channel** once — where to write (a Telegram bot and its
chats, e-mail and its addresses), with which secrets, and with what words — and a module that has
something to say declares an **event** and keeps a list of the channels to say it through. The
first such module is the Inbox: «a new submission went to every channel the form chose».

## Install

```bash
php artisan webx:module:add notifications
php artisan migrate
```

The first command installs both halves — `composer require webx-ui/module-notifications`, the npm
package written into the panel's entry by `webx:panel --sync`, and the build. By hand it is:

```bash
pnpm add @webx-ui/module-notifications
composer require webx-ui/module-notifications
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { notifications } from '@webx-ui/module-notifications'
import '@webx-ui/module-notifications/style.css'

createAdmin({
  modules: [notifications()],
})
```

The section is under **System**. Two permissions: `notifications.view` reads the channels and the
log — which holds what visitors typed into forms — and `notifications.manage` changes channels,
sends tests and retries. No role gets either on install, so only a super administrator sees the
section until somebody grants them on purpose.

## Channels

**New channel** asks for two things first: the **provider** — Telegram or e-mail, chosen once,
since a channel of another kind has other secrets and other recipients — and, optionally, the
**event**. A channel of an event knows its variables: the editor suggests them, the save refuses a
name the event does not have, and only that event offers the channel to the module that sends it.
A channel without one serves any event; a name nobody gives is empty there, and the log says so.

A new channel is switched off. It sends nothing — except a test — until it is switched on, and it
cannot be switched on before it is complete: a token, recipients, a template. The switch in the
list writes at once.

**Make a copy** gives a second channel with the same settings, switched off, its secrets carried
over encrypted on the server — they never pass through the browser. **Delete** puts a channel in
the bin: it sends nothing, the modules that chose it keep it, and it comes back to them when it is
restored. Deleted for good, it disappears from them.

### Secrets

A bot's token and an SMTP password are encrypted in the database (`APP_KEY`) and never answered:
not to the panel, not to an agent, not in the history of changes. The form shows `••••••••` for a
secret that is set; typing a new one replaces it, and **Forget** in the head's menu wipes it on the
next save. The history says that a secret changed, never what it was.

### Telegram

Create a bot with [@BotFather](https://t.me/BotFather) and paste its token. **Check the connection**
asks Telegram who the bot is.

Recipients are chat ids: a number for a person, a negative one for a group (`-100…` for a
supergroup), or the `@username` of a public channel. Nobody knows them by heart, so **Find chats**
reads the bot's last updates and lists the chats it has seen: write to the bot, or add it to a group
and write there, then search. Telegram keeps updates for a day; a bot with a webhook gives them only
to the webhook, and then the ids are typed by hand. The search sends nothing and acknowledges
nothing — a bot that is read elsewhere still gets its updates.

A message is Telegram's HTML: `<b>`, `<i>`, `<u>`, `<s>`, `<a href>`, `<code>`, `<pre>`,
`<blockquote>`, `<tg-spoiler>`. Another tag, or one left open, is refused on save. Longer than
4096 characters, the message goes as plain text, cut to fit.

### E-mail

By default a letter goes through the site's mail; **Own SMTP server** gives the channel a server of
its own. Recipients are administrators of the panel — written to at the address of their account,
whatever it becomes — and typed addresses, one letter each, so nobody sees who else got it. The body
is text: the letter wraps it in a frame, turns line breaks into lines and addresses into links.

### The address the event brings

An event can say that one of its values is an address — the e-mail a visitor typed into a form, the
Telegram of a buyer. A channel of that event can name it as a recipient («From the event: …»): that
is how the site answers the visitor who wrote, with the same template mechanism. A value that is
empty or not an address is a `skipped` line in the log, not an error.

## Templates

A template is [Mustache](https://mustache.github.io/mustache.5.html), rendered by
`mustache/mustache` on the server — never Blade, never PHP: a template is stored in the database,
and nothing in it can run code.

| Write                                             | Get                                                             |
| ------------------------------------------------- | --------------------------------------------------------------- |
| `{{form}}`, `{{ form }}`                          | the value, escaped for the provider                             |
| `{{field.phone}}`                                 | one field by its name — a dot is one level, once                |
| `{{#items}}…{{/items}}`                           | the inside once per row of a list; in it, the fields of the row |
| `{{#note}}…{{/note}}`                             | the inside when there is a value (`''` and `'0'` are none)      |
| `{{^note}}…{{/note}}`                             | the inside when there is no value, or the list is empty         |
| `{{! a note to yourself }}`                       | nothing                                                         |
| `{{{form}}}`, `{{&form}}`                         | the same as `{{form}}`: a value is always escaped               |
| `{{>partial}}`, `{{=<% %>=}}`, `{{%…}}`, `{{$…}}` | refused on save: no partials, delimiters, pragmas or blocks     |

```
Order {{number}}
{{#items}}
- {{name}} × {{qty}}: {{price}}
{{/items}}
{{^items}}
No items
{{/items}}
{{#note}}Note: {{note}}{{/note}}
```

A line with only a section tag on it leaves no empty line behind. Inside `{{#items}}` a name is
looked up in the row first, then in the event's values, so `{{number}}` there is still the order's.
A value never becomes a template: a visitor who types `{{secret}}` into a form gets those letters
printed.

### The editor

The template is a code editor with Mustache highlighted over the provider's markup — HTML for
Telegram, text for a letter — and `{{` opens the suggestions:

- the event's variables, each with its example value beside it and its description;
- a list as a whole section, the caret inside, and inside `{{#items}}` the fields of a row first;
- `{{#name}}` and `{{^name}}` for «there» and «empty»;
- after `{{/`, the section that is open.

Under the editor the same variables are buttons that insert at the caret, and what is wrong with the
template stands there as it is typed: a section left open, a close of nothing, a name the event does
not have, a partial. The save refuses the same things in the same words.

### Every language

The subject and the template have a text per language of the site; one language is enough. A
message goes out in the language its event names — the language the form was filled in, the order
was placed in — then the site's main language, then whichever the channel has.

### The preview

Beside the form (under it on a narrow screen) is the message as it would go out, drawn as it is
typed, on the event's example values: a Telegram message as a bubble of the chat, a letter as its
subject and its text. It is rendered in the browser by the same rules as on the server — the two are
held together by one set of cases both of them run (`tests/fixtures/render.json`).

### A test

**Send a test** sends now, past the queue, with the example values — to everybody on the list or to
one recipient, through what the form has, saved or not, and through a switched-off channel too,
which is what a test is for. The result for each recipient stays in the dialog. Ten tests a minute
per administrator: the button writes to real people.

## Sending

A notification goes through the queue, three attempts with a pause, one line of the log per
recipient: a chat that blocked the bot does not hold back the others, and a retry sends only to whom
it did not reach.

| `WEBX_NOTIFICATIONS_QUEUE_MODE` | What happens                                                                     |
| ------------------------------- | -------------------------------------------------------------------------------- |
| `auto` (default)                | the queue; on a `sync` queue, right after the response — the visitor never waits |
| `queue`                         | always the queue                                                                 |
| `after_response`                | always after the response: a site with a queue but no worker                     |

`php artisan webx:doctor` says when the queue has no worker, when deliveries are stuck or failing,
and when a channel's provider or event is gone.

### The log

**Log** is every line: when, which channel, to whom, the status — sent, queued, failed, skipped with
its reason — and how many attempts. A failed line opens to its error, in the provider's words and
with the token masked, and **Send again** queues it with the same values. Tests are in it too,
marked. Lines older than 90 days go every night (`webx:notifications:prune`,
`WEBX_NOTIFICATIONS_LOG_DAYS`, 0 keeps them).

The site's environment is said above the list: notifications are sent on a copy of the site too, so
a stage keeps its channels switched off or points them at a test bot.

## A module that notifies

A module declares its events from its service provider — without requiring this package, so the
module works on a site that does not have it:

```php
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Events\Variable;
use WebxUi\Notifications\NotificationEvents;

if (class_exists(NotificationEvents::class)) {
    $this->callAfterResolving(NotificationEvents::class, fn (NotificationEvents $events) => $events->register(
        new EventDefinition(
            key: 'shop.order',
            label: 'shop::notifications.event',
            module: 'shop',
            variables: [
                new Variable('number', 'shop::notifications.number', example: '1024'),
                new Variable('items', 'shop::notifications.items',
                    example: [['name' => 'Tea', 'qty' => '2']],
                    fields: ['name' => 'shop::notifications.name', 'qty' => 'shop::notifications.qty']),
                new Variable('buyer_email', 'shop::notifications.email', example: 'buyer@site.test', address: 'email'),
            ],
            sample: ['*' => ['subject' => 'Order {{number}}', 'template' => "Order {{number}}\n{{#items}}- {{name}} × {{qty}}\n{{/items}}"]],
            url: fn (array $values) => url('/cms/orders/'.($values['number'] ?? '')),
        ),
    ));
}
```

and sends when it happens, with the values as they are now and the channels it keeps in its own
data. `send()` never throws: the order is placed whatever Telegram says.

```php
app(\WebxUi\Notifications\Notifications::class)->send(
    'shop.order',
    $order->notify_channels,
    ['number' => $order->number, 'items' => $rows, 'buyer_email' => $order->email],
    subject: $order,
    locale: $order->locale,
);
```

The channels are chosen in the module's form with the picker of this package — several, with
search, only the channels of that event and the ones for any event:

```vue
<script setup lang="ts">
import { WxNotificationChannelsField } from '@webx-ui/module-notifications'
</script>

<template>
  <wx-form-item label="Notify through">
    <wx-notification-channels-field v-model="form.notify_channels" event="shop.order" />
  </wx-form-item>
</template>
```

or, in a described screen, the node `{ "type": "wx-notifications-channels", "props": { "event":
"shop.order" } }`. It needs no permission of this module: it shows a channel's name and kind, never
its recipients or its secrets. Behind it is the relation target `notify-shop-order` that every event
brings — `GET /api/cms/relations/notify-shop-order`.

### The Inbox, a consumer as it is

`webx-ui/module-inbox` is the first consumer, and the whole of it is three small classes — a pattern
to copy (`php/packages/module-inbox/src/Notifications`):

- **`SubmissionEvent`** — the definition of `inbox.submission` (the variables, the list `answers`,
  the addressee `reply_to`, the samples) and `values(Submission)`, the snapshot of one submission.
  The provider registers it under `class_exists(NotificationEvents::class)`.
- **`SendToChannels`** — a listener of the inbox's own Laravel event `SubmissionStored`: it skips
  a repeat and a submission typed in by hand, and calls `send()` with the ids the form keeps.
- **`Channels`** — everything else the inbox asks: whether the module is there at all
  (`available()`: the class exists, its provider ran, the event is declared), the ids a form may
  keep, the chosen channels named for the editor, the log of one submission
  (`deliveriesFor($submission)`) for its card, and `retry()` of its failed rows.

```php
// The listener: the consumer's own event in, send() out.
public function handle(SubmissionStored $event): void
{
    if ($event->repeated || $event->submission->source !== Submission::SOURCE_WEB) {
        return;
    }

    $this->channels->send($event->submission);
}
```

Its form keeps the ids in `options.notify_channels`; the editor, being a Vue screen rather than a
described one, draws `WxRelationsField` of `@webx-ui/module-admin` with the target
`notify-inbox-submission` — so the inbox's npm package needs nothing of this one either. The
section's guide, [Inbox](./inbox#channels-telegram-and-more), has what an administrator sees.

A provider of its own — Slack, SMS, a webhook — is a class implementing
`WebxUi\Notifications\Contracts\Provider` registered in `Providers`; its settings are described as
nodes of the form, so it appears in the panel without a line of Vue. The package's
[`AGENTS.md`](https://github.com/webx-ui/webx-ui/blob/main/php/packages/module-notifications/AGENTS.md)
has the contract.

## For agents

Everything the section does is an MCP tool: `notifications_channels_list`, `_channel_get`,
`_channel_create`, `_channel_update`, `_channel_delete`, `_channel_copy`, `_preview`, `_test`,
`_find_chats`, `_deliveries`, `_retry`, behind the scopes `notifications:read` and
`notifications:write` and the same two permissions. Secrets are write-only there too. An agent reads
`notifications://events` and `notifications://providers` first, tries a template with
`notifications_preview` and every write with `dry_run: true`.
:::
