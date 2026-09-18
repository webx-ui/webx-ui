# webx-ui/module-inbox

Forms and submissions as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel. An administrator builds a form in the panel by dragging fields into it, the site prints
it, a visitor sends it, the people named on the form get a letter, and the panel keeps the
submissions with a status, an assignee and a log.

Not a CRM: no deals, no amounts, no pipeline. "Somebody wrote in — somebody is dealing with it —
it is closed."

## Requirements

- PHP 8.3+, Laravel 13
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

## Antispam

Four layers, in the order they run:

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

The letter is a published view:

```bash
php artisan vendor:publish --tag=webx-inbox-views
```

## Configuration

```bash
php artisan vendor:publish --tag=webx-inbox-config
php artisan vendor:publish --tag=webx-inbox-lang
```

`config/webx-inbox.php` holds the intake path, the disk and its limits, the antispam defaults,
the captcha keys, the duplicate window and what `webx:inbox:prune` forgets.

## License

MIT.
