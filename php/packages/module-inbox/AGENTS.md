# webx-ui/module-inbox

Forms and submissions: an administrator builds a form in the panel from fields, the site prints
it with one Blade tag, a visitor sends it, the people named on the form get a letter, and the
panel keeps each submission with a status, an assignee and a log. Not a CRM — no deals, no
pipeline. The section «Inbox» of the panel and the MCP tools `inbox_*` edit it. Accounts and
permissions are `webx-ui/module-auth`, the panel frame `webx-ui/module-admin`, the languages
`webx-ui/localization` — read their guides for those.

## What it owns

- **Tables** `inbox_forms`, `inbox_form_fields`, `inbox_statuses`, `inbox_submissions`,
  `inbox_submission_values`, `inbox_submission_files`, `inbox_submission_events`. The migrations
  seed five statuses (New, In progress, Done, Rejected, Spam).
- **A field is a row** with a machine name: inputs are `fields[email]`, an export is headed by
  it, a field without one answers to `f{id}`. An answer keeps the field's name, label and type
  as they were when it arrived.
- **Public door** `POST {webx-inbox.path}/{slug}` (default `/webx/forms/{slug}`, route
  `webx.inbox.submit`) — the `web` group without CSRF, so pages cached whole keep working. It
  answers with the widgets' `FormResponse` (`{ ok, heading, message, redirect, html }` to the
  script, a redirect with the thank-you otherwise) and Laravel's 422 keyed `fields.<name>`.
- **Blade tag** `<x-webx-inbox::form slug="contact" />`, with `:values` for hidden fields by
  machine name and `placement="footer"` (adds `webx-inbox-form--footer`, remembered on the
  submission). An unknown or switched-off slug prints nothing.
- **The form is drawn by the widgets' form engine** (`webx-ui/widgets`, guide «Forms»):
  `<x-webx-form :csrf="false">` around an `<x-webx-field>` per field (the eleven types are the
  widgets' own), checked in the browser by the intake's own rules in the validator's words
  (`FormRules`), sent in place, the thank-you in place — in a dialog too. **The inbox's own**:
  the builder, the intake, the honeypot and the timestamp, the captcha (its place has
  `data-webx-field="captcha"`) and `{path}/inbox.js` — only the captcha, printed only with one.
  Hook analytics on `webx:form:success` of `form[data-webx-form="<slug>"]`.
- **Antispam**, in order: honeypot, timestamp, origin, captcha (only if the form asks), rate limit. A
  refusal logs its `reason` and the provider's `error-codes`/`score`. Kind of captcha, in `.env`:
  `WEBX_INBOX_RECAPTCHA_TYPE` `checkbox|invisible|v3`, `WEBX_INBOX_TURNSTILE_MODE` `managed|invisible`.
- **Attachments** on `config('webx-inbox.disk')`, never in the media library, served only through
  the panel API to `inbox.view`.
- **Letters**: `SubmissionReceived`, queued, `Reply-To` from the form's e-mail field, in the theme's
  `mail.layout` with `theme_token()` colours (`Mail\Look`), `::sample($form, $answers)` to preview.
  Saved first, a failed letter is `notify_error`; through a real queue the submission stays `queued`
  until the worker's letter reports to `Mail\Delivery` (per recipient in `notify_recipients`).
  `Submission::notification()` is one state: `none`, `queued`, `delivered`, `failed`. A form that
  reaches nobody sends nothing and logs `no_recipients` on the submission.
- **Who a form writes to** — `WebxUi\Inbox\Mail\Recipients` from `options.recipients` (`{ admin_id }`
  / `{ email }`); the API and MCP return `recipients` (each with `receives` and a `problem`:
  `admin_deleted`, `admin_inactive`, `invalid_email`) and `notifies` — `false`: nobody is told.
- **Event** `SubmissionStored`, contract `SubmissionHandler` (config `handlers`). **Panel**: one
  section, API `/api/cms/inbox`, permissions `inbox.view`, `inbox.update`, `inbox.manage`.
- **MCP** tools `inbox_forms_list`, `inbox_form_get`, `inbox_form_save`, `inbox_list`,
  `inbox_get`, `inbox_set_status`, `inbox_notify`; scopes `inbox:read`, `inbox:write`.
- **Command** `webx:inbox:prune` (`--days`, `--spam-days`, `--dry-run`) — not scheduled by default.
- **Audit** (`webx-ui/module-audit`): `inbox.no_recipients`, `inbox.notification` (`inbox_failed_days`,
  `inbox_queued_minutes`), `inbox.captcha_keys` (error), `inbox.captcha_unused` (notice),
  `inbox.spam_without_captcha` (warning; `inbox_spam_min` 3 spam + refusals in `inbox_spam_days` 30),
  `inbox.legacy_views` (warning: the site's `form.blade.php` from before the form engine).
- Also registered: a relation target for forms, notes on submissions, demo content (`resources/demo`).

## Change it without forking

| You want                                  | Do this                                                                                  |
| ----------------------------------------- | ---------------------------------------------------------------------------------------- |
| A form on a page                          | `<x-webx-inbox::form slug="..." />` in the view, or a block type that prints that tag    |
| Sent in place on a site without a theme   | `@webxWidgets` before `</body>` in the site's layout, then `php artisan webx:theme:sync` |
| Which page or product it came from        | a hidden field on the form, filled with `:values="['product' => ...]"`                   |
| A form restyled                           | classes `webx-field-*`, `webx-form-*` in the theme's CSS — like every form of the site   |
| The same form styled per place            | `placement="footer"`, then style `.webx-inbox-form--footer` in the theme's CSS           |
| Another control for one type              | the widgets' view `theme/views/vendor/webx-widgets/components/field/<type>.blade.php`    |
| Different frame of the form               | `theme/views/vendor/webx-inbox/form.blade.php`, built on `<x-webx-form>` — see Do not    |
| Bundle the captcha script yourself        | `php artisan vendor:publish --tag=webx-inbox-assets`, then `WEBX_INBOX_SCRIPT=false`     |
| A reCAPTCHA                               | `WEBX_INBOX_RECAPTCHA_KEY`, `_SECRET` and `_TYPE` matching the key kind; then the form   |
| A Turnstile                               | `WEBX_INBOX_TURNSTILE_KEY`, `WEBX_INBOX_TURNSTILE_SECRET`; then turn it on in the form   |
| Another intake path or disk               | `WEBX_INBOX_PATH`, `WEBX_INBOX_DISK`                                                     |
| Larger uploads                            | `WEBX_INBOX_MAX_SIZE` (KB); `upload.extensions`, `upload.max_files` in the config        |
| Posts from another domain of the site     | `antispam.origins` in `config/webx-inbox.php` (`--tag=webx-inbox-config`)                |
| Rate limit, duplicate window              | `antispam.throttle` (accepted), `antispam.attempts` (all), `duplicate_window`            |
| Visitors' IPs not kept whole              | `WEBX_INBOX_ANONYMISE_IP=true`                                                           |
| Forget old submissions                    | `prune.days`, `prune.spam_days`, and schedule `webx:inbox:prune` in the site             |
| A different letter                        | `theme/views/vendor/webx-inbox/mail/submission.blade.php`; colours via `$look('…')`      |
| Somebody told about a form                | `inbox_form_save` with `options.recipients`, or the editor's Notifications tab           |
| A form read only in the panel             | leave it without recipients and ignore `inbox.no_recipients` in the audit                |
| Other words                               | `php artisan vendor:publish --tag=webx-inbox-lang`                                       |
| Send submissions to a CRM or mailing list | a `SubmissionHandler` in `handlers` of the config, by slug or `*` — below                |
| Write back to the visitor (a gift, a PDF) | the same: a handler on that form's slug that sends a mailable to its e-mail field        |
| Anything else once a submission is stored | a listener of `WebxUi\Inbox\Events\SubmissionStored`, `ShouldQueue` if it calls out      |
| A letter sent again                       | the submission's menu in the panel; MCP `inbox_notify` (`dry_run` first)                 |

### Classes

The theme's contract (THEMES §10). Fields, messages and the button are the widgets' (webx-field-\*,
webx-form-\* — their AGENTS.md; the wx-form-\* of before W6.4 map to them in the guide «Inbox»). The
inbox adds its layout, in `resources/theme/inbox.css`; restyle in the theme's `src/css`:
`webx-inbox-form` (+ `webx-inbox-form--<placement>`), `webx-inbox-form__fields` (two to a row from
32rem of the form), `webx-inbox-form__field`, `webx-inbox-form__field--half`,
`webx-inbox-form__captcha`, `webx-inbox-form__actions`, `webx-inbox-form__honeypot` (leave it).

### Send submissions to a CRM

`SubmissionStored` comes once the submission, its answers and files are written (after commit);
never for what the antispam stopped, `repeated` for a double click. Name handlers per slug —
`'handlers' => ['*' => [App\Inbox\SyncToCrm::class], 'gift' => [...]]` — each a
`SubmissionHandler` (`handle(Submission $submission)`, `$submission->value('email')?->value`),
a queued job of its own, tried once: throwing becomes a `handler_error` line in the submission's
log (`handled` on success), and the visitor and the other handlers never notice. Not run again
for `repeated`. A full example — README, «After a submission is stored».

## Do not

- Do not edit anything in `vendor/webx-ui/module-inbox` or copy it into the site. The rows above
  are the supported ways; if none fits, the package is missing a seam — say so.
- Do not write a form in raw HTML posting to the intake, nor its fields, nor a `fetch`: it loses
  the honeypot, the timestamp, the captcha, the checks and the answer in place. Use the tag.
- Do not rewrite `form.blade.php` without `<x-webx-form>`: a view without it is taken for one
  published before W6.4 and gets the old script (the audit's `inbox.legacy_views` names it).
  Keep `webx_form`, `webx_locale`, `webx_page`, the honeypot, the timestamp and the captcha.
- Do not add CSRF to the intake route: a page cached whole carries a stale token and every visitor
  gets a 419 later on. The origin check and the rate limit stand in for it.
- Do not delete a form that has submissions — it is refused. Switch it off instead.
- Do not delete submissions with SQL: rows and files on the disk go together only through
  `webx:inbox:prune`. Run it with `--dry-run` first.
- Do not create submissions through MCP or the database to "test" a form: there is no such tool
  on purpose. Send the form on the site, so the antispam and the letter are tested too.
- Do not ask a form for a captcha the site has no keys for, or set a type other than the keys'
  kind ("Invalid key type"): either refuses every submission. The Antispam tab says which is set.
- Do not hook a CRM into a fork of `SubmitController` or an Eloquent `created` listener: the
  first is the vendor directory, and the second fires before any answer is written. Use the
  handlers or the event.
- Do not run handlers that call slow services on the `sync` queue: they run inside the visitor's
  request. Run a queue worker.
- Do not read `notified_at` alone: read `notification.state`. Empty with `notifies: false` (or a
  `no_recipients` log line) — no letter was ever going out; `queued` for long — no worker runs;
  `failed` after a mail settings change — a worker holds the old ones, `php artisan queue:restart`.
  Fix the cause before `inbox_notify`, or the letter fails the same way again.

## Check your work

- Send the form on the site, with and without JavaScript: the same errors under the same fields
  both ways, the thank-you, the submission in the panel, the letter (or `notify_error` says why). On a site with a queue the
  submission says `queued`, then `delivered` once a worker has sent it.
- With handlers configured: the submission's log (panel card or `inbox_get`) has a `handled` line
  per handler, or `handler_error` with the reason.
- `php artisan webx:inbox:prune --dry-run` — what the configured ages would remove.
- With MCP: `inbox_forms_list` — every switched-on form should say `notifies: true` unless it is
  meant to be read only in the panel —, then `inbox_form_get` for the fields; `inbox_form_save`,
  `inbox_set_status` and `inbox_notify` take `dry_run: true`.

## Read more

- [README.md](README.md) in this directory — the public door, antispam, attachments, letters.
- Guide: https://webx-ui.github.io/webx-ui/guide/inbox
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_INBOX.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
