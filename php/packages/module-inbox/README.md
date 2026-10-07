# webx-ui/module-inbox

Forms and submissions as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel. An administrator builds a form in the panel by dragging fields into it, the site prints
it, a visitor sends it, the people named on the form get a letter, and the panel keeps the
submissions with a status, an assignee and a log.

Not a CRM: no deals, no amounts, no pipeline. "Somebody wrote in — somebody is dealing with it —
it is closed."

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-auth`, `webx-ui/localization`, `webx-ui/mcp`

## Install

```bash
composer require webx-ui/module-inbox
php artisan migrate
```

The migrations create the `inbox_*` tables and seed five statuses — New, In progress, Done,
Rejected, Spam — in every language the package ships words in. Permissions: `inbox.view`,
`inbox.update`, `inbox.manage`.

## A field is a row, not a schema node

This is the one place the module parts company with `module-settings` and `module-blocks`, and
it is deliberate. A field has an identity that an answer points at, a position somebody drags,
and a flag saying whether it becomes a column in the list of submissions. A JSON array gives
none of those cheaply: building a column would mean walking inside a JSON document, and keeping
an answer tied to its field would mean inventing a stability its keys do not have.

Every field has a machine name, so the HTML says `fields[email]` rather than `field_17`. A site
fills its hidden fields by that name, an export is headed by it, and renaming the label breaks
neither. A field with no name of its own answers to `f{id}`.

## Answers keep a copy of the question

Beside every answer sit the field's name, label and type **as they were when it arrived**:

```php
$submission->value('email')->label;   // "Your e-mail", even after the field was renamed
```

A submission from a year ago reads with the words it was given. That is versioning of the schema
for the price of three columns — and it is why a field is deleted softly: it leaves the form and
leaves the new submissions, and stays in the ones it is already part of.

For the same reason a form that has taken submissions can be switched off but not deleted. Both
the model and a restricted foreign key say so.

## The public door

```
POST {webx-inbox.path}/{slug}          default: /webx/forms/{slug}
```

The route builds its middleware by hand: it is the `web` group minus `VerifyCsrfToken`. A page
cached whole carries the token that was minted when the cache was written, and a stale token is
a 419 that happens only on a live site and only after a while — the worst possible shape for a
bug in the one part of a site that customers use. The session stays, because a form without
JavaScript reports its errors through it.

Validation comes from the fields, and errors come back under the names the inputs have:

```json
{ "message": "…", "errors": { "fields.email": ["The e-mail must be a valid address."] } }
```

A request that asks for JSON gets JSON; anything else gets a redirect, with the thank-you or the
errors in the session.

### The same thing sent twice

Answers are hashed, and the same hash from the same form inside fifteen minutes updates the
submission it matches instead of making a second one. Outside the window it is a new submission —
without that, the same enquiry sent again next month would overwrite the first one and take its
date with it.

## The form on the site

```blade
<x-webx-inbox::form slug="contact" />
<x-webx-inbox::form :form="$form" class="my-form" :values="['product' => $product->name]" />
```

The tag prints the whole thing: the controls the fields ask for, the honeypot, the hidden
timestamp, the captcha block if the form wants one, the submit button and the two boxes a
thank-you or a refusal goes in. A slug nobody has a form for — or one that is switched off —
prints nothing, because a page that says "form not found" to a customer is worse than a page
with one section missing.

`:values` fills the hidden fields by their machine name: which page, which product, which
campaign. That is what a machine name is for.

**It works without JavaScript.** The form posts, the intake answers with a redirect, and the
page comes back with the errors under their inputs or the thank-you in place — through the
session, which is why the intake keeps one. The script the tag links adds one thing: the same
two boxes are filled without the page reloading. It is one file with no dependencies and no
build step, served straight from the package, so a site that has not rebuilt anything since
last spring still gets it.

A page may carry several forms. Each prints a hidden `webx_form`, and that is how one of them
knows that the errors — or the thank-you — in the session are its own. Keep it in a view you
rewrite, or two forms will both light up red over one refusal.

### Where the form stands

```blade
<x-webx-inbox::form slug="subscribe" placement="footer" />
```

One form of subscription in the footer and in every article is one form, and `placement` is
what tells the two apart. The `<form>` gets `wx-form--footer` beside `wx-form`, so the site
styles each by CSS alone; the views of the form and of every field get `$placement`; and a
hidden `webx_placement` makes the submission remember it — the panel shows "Placement: footer"
in the card and offers a filter once a form has come in from more than one place. The value is
a name (`[a-z][a-z0-9-]*`, up to 32 characters); anything else is dropped with a warning in the
log rather than an exception, because the form on the page matters more than a typo in one of
its attributes. `none` is reserved: it is what the filter says for "the page did not say".

### Making it yours

```bash
php artisan vendor:publish --tag=webx-inbox-views
```

What ships is the least markup that works: `wx-form`, `wx-form__field`, `wx-form__control`,
`is-invalid`, and no colours, no spacing and no stylesheet at all. The package's own design
tokens are not pulled onto the site — a site is not obliged to have them. After publishing, the
views belong to the site:

```
resources/views/vendor/webx-inbox/form.blade.php          the frame
resources/views/vendor/webx-inbox/field.blade.php         the label, the hint, the error
resources/views/vendor/webx-inbox/fields/<type>.blade.php one control each
resources/views/vendor/webx-inbox/message.blade.php       the thank-you and the refusal
resources/views/vendor/webx-inbox/honeypot.blade.php
resources/views/vendor/webx-inbox/captcha.blade.php
```

The script finds its way around by `data-webx-*` attributes and `[name]`, so a rewritten view
keeps working as long as it keeps those. Drop them and the form still submits — it just
reloads the page to say what happened.

To bundle the script instead of linking ours:

```bash
php artisan vendor:publish --tag=webx-inbox-assets   # resources/js/vendor/webx-inbox/inbox.js
```

and set `webx-inbox.script` to false.

### The captcha block

A form says which provider it wants; the site key, the secret and how the captcha runs are the
site's, in `webx-inbox.captcha.<provider>`:

| Setting                                      | Values                                  | What the visitor sees                                               |
| -------------------------------------------- | --------------------------------------- | ------------------------------------------------------------------- |
| `recaptcha.type` `WEBX_INBOX_RECAPTCHA_TYPE` | `checkbox` (default), `invisible`, `v3` | the "I'm not a robot" box; nothing (a challenge if needed); nothing |
| `recaptcha.min_score`                        | 0.5                                     | v3 only: below it the submission is a robot                         |
| `turnstile.mode` `WEBX_INBOX_TURNSTILE_MODE` | `managed` (default), `invisible`        | the widget on load; nothing unless Cloudflare asks                  |

The type has to match the keys: a reCAPTCHA key drawn as another kind is the widget's "Invalid
key type". A checkbox is drawn by the provider's own script, which the block loads once per
page. An invisible one is drawn by the form's script on the first submit, per form, and run
then, so the token is fresh; a Turnstile widget set to Invisible in Cloudflare shows nothing at
all. v3 is loaded with `?render=<key>` and asked for the action `webx_form_<slug>`. Invisible and
v3 need the form's script: without JavaScript they are refused with a sentence that says so.

A token is good once, so the script resets the widget after every answer — otherwise a visitor
who corrects one typo is refused by a captcha they already passed, which reads as a form that
simply does not work. A provider that cannot vouch for the browser is told to the visitor at once
("reload the page or try another browser") instead of a post that would be refused anyway.

## Antispam

Five layers, in the order they run:

| Layer            | What                                              | Refusal                       |
| ---------------- | ------------------------------------------------- | ----------------------------- |
| Honeypot         | a field a person never sees                       | answered "thank you", dropped |
| Timestamp        | how fast the form came back, encrypted            | 422                           |
| Origin / Referer | posted from this site's own pages                 | 422                           |
| Captcha          | reCAPTCHA or Turnstile, only if the form asks     | 422                           |
| Rate limit       | submissions a minute from one address to one form | 429                           |

The honeypot is answered as a success on purpose: telling a robot which trick was seen is what
makes the next attempt harder to catch.

The timestamp has a trap in it. On a page cached whole the mark belongs to the moment the cache
was written, not to the moment somebody opened the page, so it reads as hours old for every
visitor. A mark older than a day is therefore not judged at all, and the honeypot and the rate
limit are the layers that carry the weight.

The captcha is off unless a form asks for it, and its keys are the site's rather than the form's
— one pair for every form. A form that asks for a captcha the site has no key for refuses
submissions and says so in the log: a rubber stamp would be worse than no captcha, because
nobody would know.

Every refusal is one info line in the log with the layer that made it — `honeypot`, `too_fast`,
`origin`, `captcha_missing`, `captcha_failed`, `captcha_unconfigured` — and, for a captcha,
what the provider said (`error-codes`, a v3 `score` and `action`), never the token or the
secret. The visitor hears one sentence for all of them except the captcha they can answer: a
missing or failed one is filed under `captcha` and shown beside the widget. Refusals are also
counted per form and day in the cache, for the audit below.

With `webx-ui/module-audit` installed, three checks look at the captcha: `inbox.captcha_keys`
(error) — a switched-on form asks for a captcha whose key or secret the `.env` lacks;
`inbox.captcha_unused` (notice) — the site has keys and a switched-on form uses none;
`inbox.spam_without_captcha` (warning) — a form without a captcha had at least
`thresholds.inbox_spam_min` (3) submissions in a spam status or refusals in the last
`thresholds.inbox_spam_days` (30). There is no check for a form without a captcha as such: the
free layers are the default, and most sites never need more.

## Attachments

Files go on the module's own disk — `webx-inbox.disk`, `local` by default — under
`inbox/{form}/{submission}/{uuid}.{ext}`, never into the media library: those are files editors
chose and these are files strangers sent.

Nothing links to them. The panel reads the bytes and serves them itself:

```
GET {webx-admin.api_path}/inbox/submissions/{submission}/files/{file}      inbox.view
```

A signed address to a bucket would be one somebody forwards in a chat, and it would keep working
for its own lifetime rather than for as long as the person has the permission.

## Notifications

Recipients are named in the form's options — administrators by account, or plain addresses. Each
is written to separately, because each reads in their own language: an administrator in the
language they keep the panel in, a typed-in address in the language the submission arrived in.

The mailable is `ShouldQueue`, so a site with a queue gets one and a site on `sync` sends inline.
`Reply-To` is the value of whichever field the form calls its e-mail field — never `From`, which
is how a domain loses its reputation.

**The submission is written before anything is sent.** A mail server that is down costs a
notification, recorded on the submission as `notify_error`, and not an enquiry.

**Queued is not sent.** On a site with a queue, sending only pushes a job; the SMTP server is
asked later by a worker. So the submission says where its letters are — `notification.state` in
the panel's API and in MCP:

| state       | means                                                                          |
| ----------- | ------------------------------------------------------------------------------ |
| `none`      | nobody to write to                                                             |
| `queued`    | handed to the queue, not sent yet (`notify_queued_at` — since when)            |
| `delivered` | every letter left (`notified_at`)                                              |
| `failed`    | a letter could not be sent; `notify_error` says why, per recipient in the list |

A worker reports each letter as it settles (`SubmissionReceived::send()` / `failed()` →
`Mail\Delivery`); the log gets `notify_queued`, then `notified` with the count, and
`notify_failed` with the address. On `sync` nothing changed: the outcome is written at once.
A letter stuck as `queued` means no worker runs; one that failed after a settings change often
means a worker still holds the old ones — `php artisan queue:restart`. Then send it again: the
panel's «Send the notification again», `POST /api/cms/inbox/submissions/{id}/notify`
(`inbox.update`), or the MCP tool `inbox_notify` (with `dry_run`). With `webx-ui/module-audit`
installed, the check `inbox.notification` lists letters that failed in the last
`thresholds.inbox_failed_days` (30) days or have been queued for over
`thresholds.inbox_queued_minutes` (30).

The letter is a published view:

```bash
php artisan vendor:publish --tag=webx-inbox-views
```

## After a submission is stored

Sending a submission to a CRM, adding the address to a mailing list, writing back to the visitor
with a gift — these belong to the site, and the module gives them a seam instead of a fork.

**The event.** `WebxUi\Inbox\Events\SubmissionStored` is dispatched once the submission, its
answers and its files are written, after the commit, for every source — the form on the site and
one typed in by hand alike (`$submission->source`). What the antispam trapped or refused is never
written and never announced. The submission comes with `form`, `values` and `files` loaded.
`$event->repeated` is the same answers again inside the duplicate window — a double click.

It is not an Eloquent `created`: the row is written before its answers, so a listener of
`created` would find none of them.

A listener is an ordinary Laravel listener; one that talks to the network is `ShouldQueue`:

```php
namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use WebxUi\Inbox\Events\SubmissionStored;

final class TellTheSalesChannel implements ShouldQueue
{
    public function handle(SubmissionStored $event): void
    {
        if ($event->repeated || $event->submission->form->slug !== 'contact') {
            return;
        }

        // ...
    }
}
```

**The handlers.** For the common case — "this form goes to that service" — name classes in
`config/webx-inbox.php`, by the form's slug or by `*` for every form:

```php
'handlers' => [
    '*' => [App\Inbox\SyncToMailingList::class],
    'gift' => [App\Inbox\SendGift::class],
],
```

A handler implements `WebxUi\Inbox\Contracts\SubmissionHandler` and is built by the container, so
it asks for what it needs in its constructor:

```php
namespace App\Inbox;

use Illuminate\Support\Facades\Http;
use WebxUi\Inbox\Contracts\SubmissionHandler;
use WebxUi\Inbox\Models\Submission;

final class SyncToMailingList implements SubmissionHandler
{
    public function handle(Submission $submission): void
    {
        $email = $submission->value('email')?->value;

        if ($email === null) {
            return;
        }

        Http::withToken(config('services.mailing_list.token'))
            ->post('https://api.mailing-list.example/subscribers', [
                'email' => $email,
                'fields' => ['name' => $submission->value('name')?->value],
                'groups' => [config('services.mailing_list.groups.'.$submission->form->slug)],
            ])
            ->throw();
    }
}
```

Each handler runs as a queued job of its own, `*` first, each class once. Throwing is failing: it
becomes a `handler_error` line in the submission's log with the message, the others run all the
same, and the visitor never hears of it. Success is a `handled` line. Both show in the card in the
panel and in `inbox_get`. A handler is tried once — one that wants retries queues a job of its own
with them — and a double click does not run the handlers again.

Handlers come from the config only, never from a form's options: those are edited in the panel and
over MCP, and the panel does not choose which code runs. On the `sync` queue they run inside the
visitor's request, so a site that calls slow services runs a queue worker.

## For an agent

The section is also six MCP tools, served by `webx-ui/mcp` at `/api/cms/mcp` under the scopes
`inbox:read` and `inbox:write`:

| Tool               | What it does                                                             |
| ------------------ | ------------------------------------------------------------------------ |
| `inbox_forms_list` | Every form: slug, title, whether it is on, how much is waiting           |
| `inbox_form_get`   | One form with its questions — the shape the public door expects          |
| `inbox_form_save`  | Create or change a form and its fields in one call                       |
| `inbox_list`       | The submissions of a form: status, unread, assignee, dates, search       |
| `inbox_get`        | One submission: the answers, the files, the metadata, the notes, the log |
| `inbox_set_status` | The status, the assignee and a note, in one call                         |

They go through the same rules the panel's editor does, so a slug that is not an address is
refused here too, and everything that changes something takes `dry_run: true`.

Receiving a submission is not a tool: the intake is a public door with the antispam in front of
it, and a second way in that skipped both would be the one somebody points at a mailing list.
Deleting one is not a tool either — that is `webx:inbox:prune`, below.

## Keeping submissions

```bash
php artisan webx:inbox:prune                        # the ages from the config
php artisan webx:inbox:prune --days=365 --dry-run   # count what would go, delete nothing
```

Two ages, because they are two decisions: spam is rubbish and goes after a month, a real enquiry
is a record of a conversation and goes only when a site has said how long it keeps one. Zero means
never, and `days` is zero by default. Rows go one at a time through the model, so the files on the
disk go with them.

## Configuration

```bash
php artisan vendor:publish --tag=webx-inbox-config
php artisan vendor:publish --tag=webx-inbox-lang
```

`config/webx-inbox.php` holds the intake path, the disk and its limits, the antispam defaults,
the captcha keys, the duplicate window and what `webx:inbox:prune` forgets.

## License

MIT.
