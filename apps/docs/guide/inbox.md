# Inbox

`@webx-ui/module-inbox` is the section where a site's forms are built and what came in through
them is dealt with; `webx-ui/module-inbox` on the server is the form, the public door and the
letter. This page is both, because neither is useful alone.

An administrator builds a form in the panel by dragging fields into it, the site prints it with
one tag, a visitor sends it, the people named on the form get a letter, and the panel keeps the
submission with a status, an assignee, notes and a log. A mini-CRM in the narrow sense: not deals
and amounts, but "somebody wrote in — somebody is dealing with it — it is closed".

## Install

```bash
pnpm add @webx-ui/module-inbox
composer require webx-ui/module-inbox
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { inbox } from '@webx-ui/module-inbox'
import '@webx-ui/module-inbox/style.css'

createAdmin({
  modules: [inbox()],
})
```

The section appears at the top level, first in the menu, and the panel opens on it: a panel with
an inbox is one somebody opens in the morning to see what came in overnight, and the pages, the
blocks and the settings are what they do afterwards. A panel whose front page is something else
says `inbox({ landing: false })`.

The migration creates the `inbox_*` tables and seeds five statuses — New · In progress · Done ·
Rejected · Spam — in every language the package ships words in.

Three permissions rather than the usual two, because reading and answering is not the same job as
building the form:

| Permission     | What it opens                                                |
| -------------- | ------------------------------------------------------------ |
| `inbox.view`   | the section, the list, a submission, its files, the export   |
| `inbox.update` | the status, the assignee, the notes, deleting a submission   |
| `inbox.manage` | the forms, their fields, their settings, the statuses screen |

## A field is a row, not a schema node

This is the one place the module parts company with [screens](/guide/screens),
[`module-settings`](/guide/settings) and [`module-blocks`](/guide/blocks), and it is deliberate. A
field has an identity that an answer points at, a position somebody drags, and a flag saying
whether it becomes a column of the submission list. A JSON array gives none of those cheaply:
building a column would mean walking inside a document, and keeping an answer tied to its field
would mean inventing a stability its keys do not have.

Every field has a machine name, so the HTML says `fields[email]` and not `field_17`. A site fills
its hidden fields by that name, the CSV is headed by it, and renaming the label breaks neither. A
field nobody named answers to `f{id}`.

Eleven types: `text` · `email` · `tel` · `textarea` · `select` · `radio` · `checkbox` · `consent`
· `date` · `file` · `hidden`. A multiple answer is written twice — joined by commas in `value`,
which is what a person reads in the letter and in the export, and as an array in `payload`.

### Answers keep a copy of the question

Beside every answer sit the field's name, label and type **as they were when it arrived**:

```php
$submission->value('email')->label;   // "Your e-mail", even after the field was renamed
```

A submission from a year ago reads with the words it was given. That is versioning of the schema
for the price of three columns — and it is why a field is deleted softly: it leaves the form and
the new submissions, and stays in the ones it is already part of. For the same reason a form that
has taken submissions can be switched off but not deleted; the model and a restricted foreign key
both say so.

## The section

One screen, an explorer: the forms down the left, the submissions of the chosen one on the right.
There is no separate list of forms — everything such a screen would show is in that column, and
the trip it saves, between "what has come in" and "fix the form", is the one made most often in a
panel like this.

- **The left column** is the forms in an order somebody dragged: the title, the slug, how much has
  come through, how much nobody has read, whether it is switched off.
- **The right column** is the list, with a tab per status and two in front of them — All and
  Unread. The columns are the form's own `in_table` fields and travel with the rows, so the table
  knows what it is drawing without asking for the form. Spam is out of All by design: it is kept,
  it has its own tab, and it is not what anybody means by "what has come in".
- **A submission is a screen with its own address**, not a dialog over the list. A dialog would
  keep the filters to hand, but a submission needs a link: it goes in the letter to the recipient,
  it is pasted into a chat, an agent points at it. Going back keeps the form, the tab and the page
  you were on; the arrows in its head walk the same filtered pile.
- **On a phone** there is one column, and it is the submissions. The forms are the chooser in
  front of the list — the `filters` column of `WxListDetail` — so what folds away is them, behind
  a **Forms** button that stands in the head of the section beside **New submission**; the reader
  opens the section and sees what has come in, which is what they came for. One form is always
  open, the first unless the address names another.
- **The action the section exists for is a submission**, so that is the button in its head. A new
  form is the `+` over the list of forms — beside the things it makes one more of — because a
  panel opens this section to read what came in dozens of times for every once it adds a form.

Statuses are their own screen under `inbox.manage`: the label, the colour, and three flags —
which one a submission arrives in, which one means spam, which ones mean closed. Rows rather than
an enum, because every panel ends up wanting its own words, and the flags are what the code reads,
so a status renamed from New to «Не разобрано» goes on being the one new submissions get.

## The form on the site

```blade
<x-webx-inbox::form slug="contact" />
<x-webx-inbox::form :form="$form" class="my-form" :values="['product' => $product->name]" />
```

The tag prints the whole thing, on the widgets' form engine ([Forms](./forms)): an
`<x-webx-form>` with an `<x-webx-field>` for every field of the panel, the honeypot, the hidden
timestamp, the captcha if the form wants one, and the button. Everything a form of the site does,
this one does: it is checked in the browser by the intake's own rules and in its words, sent in
place, its errors under its fields, its thank-you in a live region — the same in a dialog. A slug nobody has a form for — or one that is switched off — prints nothing: a
page that says "form not found" to a customer is worse than a page with one section missing.

`:values` fills the hidden fields by machine name: which page, which product, which campaign. That
is what a machine name is for.

**It works without JavaScript.** The form posts, the intake answers with a redirect, and the page
comes back with the errors under their fields or the thank-you under them — through the session,
which is why the intake keeps one. With JavaScript the widgets' `form.js` does the same in place
— linked by `@webxTheme`, or on a site without a theme by `@webxWidgets` before `</body>` in its
layout ([Forms](./forms#the-form)).
The Inbox's own script, `inbox.js`, is only the captcha's half — it runs an invisible captcha on
`webx:form:submit`, the form busy until it answers, and resets it after the answer — and is linked only by a form with a captcha.

A page may carry several forms — a request in the body, a subscription in the footer, the same
form again in a dialog. Each has its own `id` (`webx-inbox-<slug>`, then `-2`…), sent back as
`_webx_form`: the errors, the old input and the thank-you in the session belong to the one that
was sent, and the others stay as the page printed them.

The form also prints a hidden `webx_locale`: the language the page came out in. The intake writes
its own middleware stack and so runs nothing the site added to its `web` group, the language
included — and how a site decides its language is the site's own business, path or header or
cookie. So the form says which one it was printed in, the intake believes it as far as a language
the site actually has, and the thank-you, the refusals and the language on the submission are the
ones the visitor was reading.

There is no code dependency on `module-blocks`: a block type in the constructor prints the same
tag, so the form works on a site with no block editor at all.

### Where the form stands

```blade
<x-webx-inbox::form slug="subscribe" placement="footer" />
```

One subscription form in the footer and in every article is one form, and `placement` is what
tells the two apart. The `<form>` gets `webx-inbox-form--footer` beside `webx-inbox-form`, so
the site styles each by CSS alone; the view of the form gets `$placement`; and a hidden
`webx_placement` makes the submission remember it — the card of a submission says
«Placement: footer», and the list offers a filter once a form has come in from more than one
place. `inbox_get` and `inbox_list` return it, and `inbox_list` filters by it.

The value is a name — `[a-z][a-z0-9-]*`, up to 32 characters. Anything else is dropped with a
warning in the log rather than an exception: the form on the page matters more than a typo in one
of its attributes. `none` is reserved; it is what the filter says for «the page did not say».

This is what a footer made of blocks needs from the inbox: a block whose template prints
`<x-webx-inbox::form :slug="$form" placement="footer" />`, styled by `.webx-inbox-form--footer` — see
[Layout regions](/guide/layout-regions).

### The look of the form

The fields, the messages and the button are the widgets' — `webx-field-*` and `webx-form-*`, the
same classes as every form of the site ([Forms](./forms), «The look» and «The look of the form»).
On a site with a theme the Inbox adds only its layout, `resources/theme/inbox.css` on the site
tokens: fields two to a row where the panel marked them half width and the form is wide enough. A
theme restyles all of it in its `src/css` ([where the styles live](./styles.md#a-module-s-page)):

| Class                                                    | What                                            |
| -------------------------------------------------------- | ----------------------------------------------- |
| `webx-inbox-form`, `webx-inbox-form--<placement>`        | on the `<form>`, beside `webx-form`             |
| `webx-inbox-form__fields`                                | the fields, two to a row from 32rem of the form |
| `webx-inbox-form__field`, `webx-inbox-form__field--half` | on each `webx-field`; half width in the panel   |
| `webx-inbox-form__captcha`                               | the captcha and its error                       |
| `webx-inbox-form__actions`                               | the button's row                                |
| `webx-inbox-form__honeypot`                              | the field for robots, clipped inline — leave it |

**Before W6.4 the form had classes of its own, `wx-form*`.** They map like this:

| Was                                                         | Now                                                          |
| ----------------------------------------------------------- | ------------------------------------------------------------ |
| `wx-form`, `wx-form--<placement>`                           | `webx-form webx-inbox-form`, `webx-inbox-form--<placement>`  |
| `wx-form__fields`, `wx-form__field`, `wx-form__field--half` | `webx-inbox-form__fields`, `__field`, `__field--half`        |
| `wx-form__field--<type>`, `is-invalid`                      | `webx-field--<type>`, `is-invalid` on `webx-field`           |
| `wx-form__label`, `__required`, `__help`, `__error`         | `webx-field__label`, `__required`, `__hint`, `__error`       |
| `wx-form__control`, `wx-form__control--file`                | `webx-field__control`, `webx-field__control--file`           |
| `wx-form__choices`, `__choice`, `__choice-input`            | `webx-field__choices`, `__choice`, `__check`                 |
| `wx-form__choice-label`, `__consent`, `__consent-input`     | `webx-field__choice-label`, `__choice--consent`, `__check`   |
| `wx-form__consent-text`                                     | `webx-field__choice-label` inside `__choice--consent`        |
| `wx-form__captcha`, `__actions`, `__honeypot`               | `webx-inbox-form__captcha`, `__actions`, `__honeypot`        |
| `wx-form__message`, `__message-heading`, `__message-text`   | `webx-form__message` > `__answer` > `__heading`, `__text`    |
| `wx-form__error--form`                                      | `webx-form__error` > `__refusals` > `__refusal`              |
| `wx-form__submit` (`:disabled` while sending)               | `webx-form__submit` (`aria-disabled`; the form `is-sending`) |

A site that published `form.blade.php` before then keeps a working form for an alias period: a
view without `<x-webx-form>` in it is handed what it used to be handed, the old partials stay in
the package and its old script is served as `inbox-legacy.js` — and the audit's
`inbox.legacy_views` names the view until it is moved. Moving it is deleting it, or rewriting it
around `<x-webx-form>`, and repainting the classes by the table above.

### Making the markup yours

Most of what a site wants is CSS. One control of one type is the widgets' view
(`theme/views/vendor/webx-widgets/components/field/<type>.blade.php`) and changes every form of the
site alike. The frame is the Inbox's:

```bash
php artisan vendor:publish --tag=webx-inbox-views
```

After it `form.blade.php` (with `form/captcha.blade.php` and `form/honeypot.blade.php`) belongs to
the project and gets no fixes. Keep `<x-webx-form>` and the fields in it, the hidden `webx_form`,
`webx_locale` and `webx_page`, the honeypot, the timestamp and the captcha's place
(`data-webx-field="captcha"`). To bundle `inbox.js` instead of linking the package's, publish it
with `--tag=webx-inbox-assets` and set `webx-inbox.script` to false.

The panel's Embedding tab prints the snippet for the form you are looking at, with its machine
names in it, so none of this has to be remembered.

## The public door

```
POST /webx/forms/{slug}          the prefix is webx-inbox.path
```

The route builds its middleware by hand: the `web` group **minus** `VerifyCsrfToken`. A page
cached whole carries the token minted when the cache was written, and a stale token is a 419 that
happens only on a live site and only after a while — the worst possible shape for a bug in the one
part of a site that customers use. The session stays, because a form without JavaScript reports
its errors through it.

Validation comes from the fields, and errors come back under the names the inputs have:

```json
{ "message": "…", "errors": { "fields.email": ["The e-mail must be a valid address."] } }
```

The answer is the one every form of the site gets, `FormResponse` of the widgets: a request
that asks for JSON gets `{ ok, heading, message, redirect, html }` — the form's thank-you, or its
address when it has one — and anything else a redirect: there, or back to the page with the
thank-you in the session. A form whose thank-you nobody wrote still says «Thank you».

**The same thing sent twice.** Answers are hashed, and the same hash from the same form inside
fifteen minutes updates the submission it matches rather than making a second one. Outside that
window it is a new submission: without a window, the same enquiry sent again next month would
overwrite the first and take its date with it.

## Antispam

Five layers, in the order they run:

| Layer            | What                                              | Refusal                       |
| ---------------- | ------------------------------------------------- | ----------------------------- |
| Honeypot         | a field a person never sees                       | answered "thank you", dropped |
| Timestamp        | how fast the form came back, encrypted            | 422                           |
| Origin / Referer | posted from this site's own pages                 | 422                           |
| Captcha          | reCAPTCHA or Turnstile, only if the form asks     | 422                           |
| Rate limit       | submissions a minute from one address to one form | 429                           |
| Attempt limit    | any requests a minute, refused ones included      | 429                           |

The honeypot is answered as a success on purpose: telling a robot which trick was seen is what
makes the next attempt harder to catch.

The timestamp has a trap in it. On a page cached whole the mark belongs to the moment the cache
was written and not to the moment somebody opened the page, so it reads as hours old for every
visitor. A mark older than a day is therefore not judged at all, and the honeypot and the rate
limit carry the weight. A POST with no mark at all is refused: every form the package draws carries
one, cached or not, so that request was written straight to the address.

The rate limit counts only submissions that got through (`throttle`, 5 a minute): somebody who
mistypes their address five times is correcting a mistake, not flooding the form. Everything that
knocks, refused or not, counts towards the second, higher limit (`attempts`, 20 a minute).

The captcha is off unless a form asks for it, and its keys are the site's rather than the form's —
one pair for every form, mapped from the Integrations group of
[`module-settings`](/guide/settings) rather than repeated in each form's options. A form that asks
for a captcha the site has no key for refuses submissions and says so, because a rubber stamp
would be worse: nobody would know.

## Attachments

Files go on the module's own disk — `webx-inbox.disk`, `local` by default — under
`inbox/{form}/{submission}/`, and never into [the media library](/guide/media): those are files
editors chose and these are files strangers sent. Nothing links to them; the panel reads the bytes
and serves them itself, behind `inbox.view`:

```
GET /api/cms/inbox/submissions/{submission}/files/{file}
```

A signed address to a bucket is one somebody forwards in a chat, and it keeps working for its own
lifetime rather than for as long as the person has the permission. Deleting a submission deletes
its files — through the model, because a database cascade would take the rows and leave the bytes.

## Notifications

Recipients are named in the form's options: administrators by account, so the address follows the
account, or plain addresses typed in. Each is written to separately, because each reads in their
own language — an administrator in the language they keep the panel in, a typed-in address in the
language the submission arrived in.

The mailable is `ShouldQueue`, so a site with a queue gets one and a site on `sync` sends inline.
`Reply-To` is the value of whichever field the form calls its e-mail field — never `From`, which
is how a domain loses its reputation.

**The submission is written before anything is sent.** A mail server that is down costs a
notification, recorded on the submission as `notify_error` and shown on its screen, and not an
enquiry.

**A form that names nobody says so.** No recipients — or only administrators deleted or switched
off since — sends nothing and logs `no_recipients` on the submission, so it does not look like a
letter waiting in the queue. Every form in the panel API and in MCP carries `recipients` (each
with `receives` and a `problem`) and `notifies`; the editor warns above its tabs, the column of
forms marks the form, and the site audit reports it as `inbox.no_recipients`.

**Queued is not sent.** On a site with a queue, sending a letter only pushes a job, and a worker
talks to the SMTP server later. The submission therefore carries a state — `none`, `queued`,
`delivered` or `failed` with the reason — and the list of recipients with how each letter went;
the screen shows both, and so do `inbox_get` and `inbox_list`. A letter that stays `queued` means
no queue worker runs. One that failed right after the mail settings changed usually means a
worker still holds the old ones: `php artisan queue:restart`. Then send it again from the
submission's menu, or with the MCP tool `inbox_notify` (`dry_run` first). With the site audit
installed, the check «Notifications about submissions that did not leave» lists both cases.

**The letter wears the site's theme.** It stands in the theme's frame — the site's name, the
letter on a sheet, a line below — and every colour in it is a token's value written on the
server, because a mail client reads no CSS variable: a preset or the accent picked in the panel
repaints the letter with the site. Without a theme it has a frame of its own in the default
theme's colours. A theme rewrites the letter in `views/vendor/webx-inbox/mail/submission.blade.php`
(the styles guide, «Letters»); the starter site's «Kitchen sink › Mail» shows it.

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

## The panel's API

```
GET    /api/cms/inbox/forms                             the column, with both counts
POST   /api/cms/inbox/forms                             · PUT/DELETE /{form} · POST /{form}/duplicate
GET    /api/cms/inbox/forms/{form}/fields               POST · PUT /fields/{field} · DELETE
POST   /api/cms/inbox/forms/{form}/fields/sorting       the order somebody dragged
GET    /api/cms/inbox/forms/{form}/submissions          filters, search, the columns, the counts
POST   /api/cms/inbox/forms/{form}/submissions          one typed in by hand
GET    /api/cms/inbox/forms/{form}/submissions/export   CSV
GET    /api/cms/inbox/submissions/{submission}          PUT: status, assignee, a corrected answer
POST   /api/cms/inbox/submissions/{submission}/notify   the notification again (inbox.update)
POST   /api/cms/inbox/submissions/mass                  a status or a delete over a selection
GET    /api/cms/inbox/statuses                          POST · PUT · DELETE · /sorting
POST   /api/cms/entities/inbox_submission/{id}/notes    the shared notes route of module-admin
```

The list is the Laravel paginator as it comes, which is what `WxTable` expects. A submission typed
in by hand goes through the same intake as the public door — the same rules, the same snapshot,
the same log line — and differs only in its `source`, which is what tells the two apart afterwards.

## For an agent: MCP

The section is also a set of tools. With `webx-ui/mcp` installed (it comes with this module), the
panel serves one MCP server at `/api/cms/mcp`:

Passport comes with the panel, and a site switches it on once:

```bash
php artisan vendor:publish --tag=passport-migrations && php artisan migrate
php artisan passport:keys
```

Somebody then connects their own agent to it: they paste that address into Claude, ChatGPT or
`claude mcp add --transport http webx <address>`, the client sends them to the panel to sign in
and agree, and it leaves with a token of theirs. The agent acts as that administrator. On the
machine the site runs on, `php artisan mcp:start webx` is the same server over stdio, trusted the
way tinker is.

| Tool               | What it does                                                                    |
| ------------------ | ------------------------------------------------------------------------------- |
| `inbox_forms_list` | Every form: slug, title in each language, whether it is on, how much is waiting |
| `inbox_form_get`   | One form with its questions — the shape of the data the public door expects     |
| `inbox_form_save`  | Create or change a form and its fields in one call                              |
| `inbox_list`       | The submissions of a form: status, unread, assignee, dates, search              |
| `inbox_get`        | One submission: the answers, the files, the metadata, the notes, the log        |
| `inbox_set_status` | The status, the assignee and a note, in one call                                |
| `inbox_notify`     | The notification again, for a letter that failed or is stuck; `dry_run` first   |

A form is named by its id or by its slug, and the tools go through the same rules the panel's
editor does: a slug that is not an address and a recipient nobody can be written to are refused
here too. A save changes only what it names — a field nobody mentioned is left alone, a field
matched by id or machine name is updated, one sent with `remove: true` is put aside so the answers
given through it stay readable. Everything that changes something takes `dry_run: true` and then
reports what it would have done.

**Two things are deliberately missing.** Receiving a submission is not a tool: the intake is a
public door with the antispam in front of it, and a second way in that skipped both would be the
one somebody points at a mailing list. Deleting a submission is not one either — it takes the
files with it and cannot be undone; the panel asks a person, and `webx:inbox:prune` is the
deliberate, configured version of the same thing.

## Keeping submissions

Submissions hold somebody else's name, telephone number and address, so a panel that keeps them
for ever keeps them for ever by accident rather than by decision:

```bash
php artisan webx:inbox:prune                        # the ages from the config
php artisan webx:inbox:prune --days=365 --dry-run   # count what would go, delete nothing
```

Two ages, because they are two different decisions: spam is rubbish and goes after a month, and a
real enquiry is a record of a conversation and goes only when a site has said how long it keeps
one. Zero means never, and `days` is zero by default — deleting a client's enquiries is not a
thing to start doing by surprise. Rows go one at a time through the model, so the files on the
disk go with them.

The address of the visitor is written into the submission's metadata because the antispam needs
one and an abuse report needs one. A site that would rather not keep it sets `anonymise_ip`, and
then the last octet is dropped before it is written: enough to tell two visitors apart, not enough
to tell which flat they are in.

## Config

`config/webx-inbox.php`:

| Key                | Default           | What it is                                                      |
| ------------------ | ----------------- | --------------------------------------------------------------- |
| `path`             | `webx/forms`      | The prefix every form posts under                               |
| `disk` · `prefix`  | `local` · `inbox` | Where attachments live                                          |
| `upload`           | 10 MB, 10 files   | The size and the white list of extensions                       |
| `antispam`         | see above         | Honeypot name, `min_seconds`, `throttle`, `attempts`, `origins` |
| `captcha`          | off               | The site keys for reCAPTCHA and Turnstile                       |
| `script`           | `true`            | Whether the tag links the package's own script                  |
| `duplicate_window` | `900`             | Seconds in which the same answers are the same thing            |
| `prune`            | 30 · 0            | Days for spam, days for everything; 0 means never               |
| `anonymise_ip`     | `false`           | Drop the last octet before writing the address                  |
| `handlers`         | `[]`              | Handlers by form slug or `*`, run once it is stored             |

A form may lower or raise its own antispam settings; everything else here is the site's.

## What is deferred

- **A kanban of submissions.** `WxKanban` exists and the data is ready; it needs a screen.
- **Answering a client from the panel** — that is a conversation, with IMAP behind it. A letter
  back to the visitor on arrival is already possible: a handler on that form's slug.
- **Visitors' comments** are a different module: different readers, different permissions, a
  different life. Administrators' notes are not a public thread.
- **Telegram and webhooks** as channels beside mail.
- **A badge in the menu** counting what is new: the panel's manifest has no badges yet, and adding
  them for one section is a job in `module-admin`.
- Multi-step forms, conditional fields, calculated fields, A/B forms.
