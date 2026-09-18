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
- **On a phone** there are no two columns: the forms, then the submissions of one of them, then
  back — the same `WxListDetail` the panel already has.

Statuses are their own screen under `inbox.manage`: the label, the colour, and three flags —
which one a submission arrives in, which one means spam, which ones mean closed. Rows rather than
an enum, because every panel ends up wanting its own words, and the flags are what the code reads,
so a status renamed from New to «Не разобрано» goes on being the one new submissions get.

## The form on the site

```blade
<x-webx-form slug="contact" />
<x-webx-form :form="$form" class="my-form" :values="['product' => $product->name]" />
```

The tag prints the whole thing: the controls the fields ask for, the honeypot, the hidden
timestamp, the captcha block if the form wants one, the button and the two boxes a thank-you or a
refusal goes in. A slug nobody has a form for — or one that is switched off — prints nothing: a
page that says "form not found" to a customer is worse than a page with one section missing.

`:values` fills the hidden fields by machine name: which page, which product, which campaign. That
is what a machine name is for.

**It works without JavaScript.** The form posts, the intake answers with a redirect, and the page
comes back with the errors under their inputs or the thank-you in place — through the session,
which is why the intake keeps one. The script the tag links adds one thing: the same two boxes
filled without the page reloading. One file, no dependencies, no build step, served straight from
the package.

A page may carry several forms. Each prints a hidden `webx_form`, and that is how one of them
knows the errors in the session are its own. Two forms on one page without it both light up red
over one refusal — and with one form on the page you will never see it.

There is no code dependency on `module-blocks`: a block type in the constructor prints the same
tag, so the form works on a site with no block editor at all.

### Making the markup yours

```bash
php artisan vendor:publish --tag=webx-inbox-views
```

What ships is the least markup that works — `wx-form`, `wx-form__field`, `wx-form__control`,
`is-invalid` — with no colours, no spacing and no stylesheet. The package's design tokens are not
pulled onto the site: a site is not obliged to have them. After publishing, the views belong to
the project:

```
resources/views/vendor/webx-inbox/form.blade.php            the frame
resources/views/vendor/webx-inbox/field.blade.php           the label, the hint, the error
resources/views/vendor/webx-inbox/fields/<type>.blade.php   one control each
resources/views/vendor/webx-inbox/message.blade.php         the thank-you and the refusal
resources/views/vendor/webx-inbox/honeypot.blade.php · captcha.blade.php
```

The script finds its way around by `data-webx-*` attributes and by `[name]`, so a rewritten view
keeps working as long as it keeps those. Drop them and the form still submits — it just reloads
the page to say what happened. To bundle the script instead of linking the package's, publish it
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

A request that asks for JSON gets JSON — `{ ok, heading, message, redirect }` — and anything else
gets a redirect with the same thing in the session.

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

The honeypot is answered as a success on purpose: telling a robot which trick was seen is what
makes the next attempt harder to catch.

The timestamp has a trap in it. On a page cached whole the mark belongs to the moment the cache
was written and not to the moment somebody opened the page, so it reads as hours old for every
visitor. A mark older than a day is therefore not judged at all, and the honeypot and the rate
limit carry the weight.

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

```bash
php artisan vendor:publish --tag=sanctum-migrations && php artisan migrate   # once per site
php artisan webx:mcp:token admin@example.com --name=claude
```

The agent then acts as that administrator, within the token's scopes: `inbox:read` for the tools
that look, `inbox:write` for the ones that change.

| Tool               | What it does                                                                    |
| ------------------ | ------------------------------------------------------------------------------- |
| `inbox_forms_list` | Every form: slug, title in each language, whether it is on, how much is waiting |
| `inbox_form_get`   | One form with its questions — the shape of the data the public door expects     |
| `inbox_form_save`  | Create or change a form and its fields in one call                              |
| `inbox_list`       | The submissions of a form: status, unread, assignee, dates, search              |
| `inbox_get`        | One submission: the answers, the files, the metadata, the notes, the log        |
| `inbox_set_status` | The status, the assignee and a note, in one call                                |

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

| Key                | Default           | What it is                                           |
| ------------------ | ----------------- | ---------------------------------------------------- |
| `path`             | `webx/forms`      | The prefix every form posts under                    |
| `disk` · `prefix`  | `local` · `inbox` | Where attachments live                               |
| `upload`           | 10 MB, 10 files   | The size and the white list of extensions            |
| `antispam`         | see above         | Honeypot name, `min_seconds`, `throttle`, `origins`  |
| `captcha`          | off               | The site keys for reCAPTCHA and Turnstile            |
| `script`           | `true`            | Whether the tag links the package's own script       |
| `duplicate_window` | `900`             | Seconds in which the same answers are the same thing |
| `prune`            | 30 · 0            | Days for spam, days for everything; 0 means never    |
| `anonymise_ip`     | `false`           | Drop the last octet before writing the address       |

A form may lower or raise its own antispam settings; everything else here is the site's.

## What is deferred

- **A kanban of submissions.** `WxKanban` exists and the data is ready; it needs a screen.
- **An auto-reply to the sender**, and answering a client from the panel at all — that is a
  conversation, with IMAP behind it.
- **Visitors' comments** are a different module: different readers, different permissions, a
  different life. Administrators' notes are not a public thread.
- **Telegram and webhooks** as channels beside mail.
- **A badge in the menu** counting what is new: the panel's manifest has no badges yet, and adding
  them for one section is a job in `module-admin`.
- Multi-step forms, conditional fields, calculated fields, A/B forms.
