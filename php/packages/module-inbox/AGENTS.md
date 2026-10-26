# webx-ui/module-inbox

Forms and submissions: an administrator builds a form in the panel, the site prints it with one
Blade tag, a visitor sends it, the people named on the form get a letter (and the form's channels a
message, with `webx-ui/module-notifications`), the panel keeps each submission with a status, an
assignee and a log. Not a CRM. The section «Inbox» and the MCP tools `inbox_*` edit it; accounts
are `webx-ui/module-auth`, the frame `webx-ui/module-admin`, languages `webx-ui/localization`.

## What it owns

- **Tables** `inbox_forms`, `inbox_form_fields`, `inbox_statuses`, `inbox_submissions`,
  `inbox_submission_values`, `inbox_submission_files`, `inbox_submission_events`. The migrations
  seed five statuses (New, In progress, Done, Rejected, Spam).
- **A field is a row** with a machine name (`fields[email]`, the export, `{{field.<name>}}`): a new one without it is
  named from its label (`Fields\FieldNames`, `_2` if taken). An answer keeps its field's id, name, label and type.
- **Public door** `POST {webx-inbox.path}/{slug}` (default `/webx/forms/{slug}`, `webx.inbox.submit`) — the
  `web` group without CSRF, so pages cached whole keep working. Answers the widgets' `FormResponse`
  (`{ ok, heading, heading_html, message, redirect, html }`), 422 keyed `fields.<name>`. `{{field.<name>}}` in `thank-you.heading`/`thank-you.text` is the visitor's answer, escaped, in `<em class="webx-form__answer-value">` (`Submissions\ThankYou`); empty, unknown, a file — nothing. `options.redirect` is a menu-style link: a record in the submission's language, else the main one.
- **Blade tag** `<x-webx-inbox::form slug="contact" />`, with `:values` for hidden fields by
  machine name and `placement="footer"` (adds `webx-inbox-form--footer`, remembered on the
  submission), `again` — «Send another» after the thank-you (`<x-webx-form again>`). An unknown or switched-off slug prints nothing. `options.description` — a line over the fields (in a dialog under its title; `:description="false"` drops it).
- **The form is drawn by the widgets' form engine** (`webx-ui/widgets`, guide «Forms»):
  `<x-webx-form :csrf="false">` around an `<x-webx-field>` per field (the eleven types are the
  widgets' own), checked in the browser by the intake's own rules in the validator's words
  (`FormRules`), sent in place, the thank-you in place — in a dialog too. **The inbox's own**:
  the builder, the intake, the honeypot and the timestamp, the captcha (its place has
  `data-webx-field="captcha"`) and `{path}/inbox.js` — only the captcha, printed only with one;
  the form is busy while it runs (`detail.wait`). Hook analytics on `webx:form:success` of `form[data-webx-form="<slug>"]`.
- **Antispam**, in order: honeypot, timestamp, origin, captcha (only if the form asks), rate limit. A
  refusal logs its `reason` and the provider's `error-codes`/`score`. Kind of captcha, in `.env`:
  `WEBX_INBOX_RECAPTCHA_TYPE` `checkbox|invisible|v3`, `WEBX_INBOX_TURNSTILE_MODE` `managed|invisible`.
- **Attachments** on `config('webx-inbox.disk')`, never in the media library, served only by the panel.
- **Letters**: `SubmissionReceived`, queued, `Reply-To` from the form's e-mail field, in the theme's
  `mail.layout` with `theme_token()` colours (`Mail\Look`), `::sample($form, $answers)` to preview.
  Saved first, a failed letter is `notify_error`; through a real queue it stays `queued` until the
  worker's letter reports to `Mail\Delivery` (per recipient in `notify_recipients`). `notification()`
  is one state: `none`, `queued`, `delivered`, `failed`, plus `hint`, `stalled` (queued over 5 min), `trapped` (a recipient's `trap`: `log`, a sandbox) and `warnings` — from the frame's `Mail\MailRoutes`, as the form's `mail` (route, warnings, queue) and `inbox_get` → `warnings`; nobody named logs `no_recipients`.
- **Who a form writes to** — `WebxUi\Inbox\Mail\Recipients` from `options.recipients` (`{ admin_id }`
  / `{ email }`); the API and MCP return `recipients` (each with `receives` and a `problem`:
  `admin_deleted`, `admin_inactive`, `invalid_email`) and `notifies` — `false`: nobody is told.
- **Event** `SubmissionStored` — always fired; `SubmissionHandler` (config `handlers`). **Panel**: `/api/cms/inbox`.
- **Channels** — only with `webx-ui/module-notifications` (a `suggest`): event `inbox.submission`
  (`Notifications\SubmissionEvent`), ids in `options.notify_channels`, sent by `SendToChannels` for
  the site's form, not a repeat; `channels` on a form and a submission (null without the module). One way
  per form: `options.notify_via` `email`|`channels`; `channels` without the module writes letters (`webx:doctor` warns).
  Default templates (HTML letter, Telegram, text): `Notifications\SubmissionTemplates`, words `mail.*`.
- **MCP** tools `inbox_forms_list`, `inbox_form_get`, `inbox_form_save`, `inbox_list`, `inbox_get`,
  `inbox_set_status`, `inbox_notify`; scopes `inbox:read`, `inbox:write`. **Command** `webx:inbox:prune` (`--days`, `--spam-days`, `--dry-run`) — not scheduled by default.
- **Audit** (`webx-ui/module-audit`): `inbox.no_recipients`, `inbox.notification` (`inbox_failed_days`,
  `inbox_queued_minutes`), `inbox.captcha_keys` (error), `inbox.captcha_unused` (notice), `inbox.spam_without_captcha`
  (warning; `inbox_spam_min` 3 spam + refusals in `inbox_spam_days` 30), `inbox.legacy_views` (warning: a pre-engine `form.blade.php`).
- **Field type** `wx-inbox-form` (`Screens\FormFieldType`; component in `@webx-ui/module-inbox`): a form
  chosen by name, kept by **slug**; a slug no form has is refused when new, kept when already held. Also: relation target
  `inbox-form` (rows with `slug`), notes on submissions, demo content.

### Permissions

| Permission     | Opens                                                                                                                                                                                  |
| -------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `inbox.view`   | the forms and statuses lists, a form's submissions and their export, a submission, its attachments (nobody else gets the files); `inbox_forms_list`, `inbox_list`, `inbox_get`         |
| `inbox.update` | dealing with submissions: change (status, assignee, values), delete, mass actions, add one by hand, send the letter again; `inbox_set_status`, `inbox_notify`                          |
| `inbox.manage` | what the section is: forms (create, edit, duplicate, reorder, delete), fields, statuses, the recipients; `inbox_forms_list`, `inbox_form_get`, `inbox_form_save` — not the submissions |

The form picker (relation target `inbox-form`, field `wx-inbox-form`) asks for none. A route or
screen node the site adds next to the module asks the same: `cms.can:inbox.view` to read
submissions, `cms.can:inbox.update` to change them, `cms.can:inbox.manage` for forms and statuses.

## Change it without forking

| You want                                    | Do this                                                                                                                                                    |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A form on a page                            | `<x-webx-inbox::form slug="..." />` in the view, or a block with a `wx-inbox-form` field                                                                   |
| Sent in place on a site without a theme     | `@webxWidgets` before `</body>` in the site's layout, then `php artisan webx:theme:sync`                                                                   |
| Which page or product it came from          | a hidden field on the form, filled with `:values="['product' => ...]"`                                                                                     |
| A form restyled                             | classes `webx-field-*`, `webx-form-*` in the theme's CSS — like every form of the site                                                                     |
| The same form styled per place              | `placement="footer"`, then style `.webx-inbox-form--footer` in the theme's CSS                                                                             |
| Another control for one type                | the widgets' view `theme/views/vendor/webx-widgets/components/field/<type>.blade.php`                                                                      |
| Different frame of the form                 | `theme/views/vendor/webx-inbox/form.blade.php`, built on `<x-webx-form>` — see Do not                                                                      |
| Bundle the captcha script yourself          | `php artisan vendor:publish --tag=webx-inbox-assets`, then `WEBX_INBOX_SCRIPT=false`                                                                       |
| A captcha                                   | reCAPTCHA: `WEBX_INBOX_RECAPTCHA_KEY`, `_SECRET`, `_TYPE` of the key's kind; Turnstile: `WEBX_INBOX_TURNSTILE_KEY`, `_SECRET`; then turn it on in the form |
| Another intake path or disk, larger uploads | `WEBX_INBOX_PATH`, `WEBX_INBOX_DISK`; `WEBX_INBOX_MAX_SIZE` (KB), `upload.extensions`, `upload.max_files`                                                  |
| Other origins, rate limit, duplicates       | `antispam.origins`, `antispam.throttle` (accepted), `antispam.attempts` (all), `duplicate_window` (`--tag=webx-inbox-config`)                              |
| Visitors' IPs not kept whole                | `WEBX_INBOX_ANONYMISE_IP=true`                                                                                                                             |
| Forget old submissions                      | `prune.days`, `prune.spam_days`, and schedule `webx:inbox:prune` in the site                                                                               |
| A different letter                          | `theme/views/vendor/webx-inbox/mail/submission.blade.php`; colours via `$look('…')`                                                                        |
| Somebody told about a form                  | `inbox_form_save` with `options.recipients`, or the editor's Notifications tab                                                                             |
| A form read only in the panel               | leave it without recipients and ignore `inbox.no_recipients` in the audit                                                                                  |
| Other words                                 | `php artisan vendor:publish --tag=webx-inbox-lang`                                                                                                         |
| Send submissions to a CRM or mailing list   | a `SubmissionHandler` in `handlers` of the config, by slug or `*` — below                                                                                  |
| Write back to the visitor (a gift, a PDF)   | the same: a handler on that form's slug that sends a mailable to its e-mail field                                                                          |
| Anything else once a submission is stored   | a listener of `WebxUi\Inbox\Events\SubmissionStored` (`Event::listen`), `ShouldQueue`                                                                      |
| A submission in Telegram instead of letters | a channel of `inbox.submission`, then `inbox_form_save` `options.notify_via: channels`, `notify_channels`                                                  |
| A letter sent again                         | the submission's menu in the panel; MCP `inbox_notify` (`dry_run` first)                                                                                   |

### Classes

The theme's contract (THEMES §10). Fields, messages and the button are the widgets' (webx-field-\*,
webx-form-\* — their AGENTS.md; the wx-form-\* of before W6.4 map to them in the guide «Inbox»). The
inbox adds its layout, in `resources/theme/inbox.css`; restyle in the theme's `src/css`: `webx-inbox-form`
(+ `webx-inbox-form--<placement>`), `webx-inbox-form__description`, `webx-inbox-form__fields` (two to a row from 24rem of the form), `webx-inbox-form__field`,
`webx-inbox-form__field--half`, `webx-inbox-form__captcha`, `webx-inbox-form__actions`, `webx-inbox-form__honeypot` (leave it).

### Send submissions to a CRM

`SubmissionStored` comes once the submission, its answers and files are written (after commit);
never for what the antispam stopped, `repeated` for a double click. Handlers per slug —
`'handlers' => ['*' => [App\Inbox\SyncToCrm::class], 'gift' => [...]]`, each a `SubmissionHandler`
(`handle(Submission $submission)`), a queued job tried once: throwing is a `handler_error` line in
the log (`handled` on success); the visitor never notices. Example — guide, «After a submission…».

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
- Do not delete submissions with SQL: rows and files go together only through `webx:inbox:prune`.
- Do not create submissions through MCP or the database to "test" a form: there is no such tool
  on purpose. Send the form on the site, so the antispam and the letter are tested too.
- Do not ask a form for a captcha the site has no keys for, or set a type other than the keys'
  kind ("Invalid key type"): either refuses every submission. The Antispam tab says which is set.
- Do not hook a CRM into a fork of `SubmitController` or an Eloquent `created` listener (it fires
  before any answer is written). Use the handlers or the event.
- Do not run handlers that call slow services on the `sync` queue (the visitor's request): run a worker.
- Do not read `notified_at` alone: read `notification.state`. Empty with `notifies: false` (or a
  `no_recipients` log line) — no letter was ever going out; `queued` for long — no worker runs; `failed`
  after a mail settings change — a worker holds the old ones, `php artisan queue:restart`. Fix the cause before `inbox_notify`.

## Check your work

- Send the form on the site, with and without JavaScript: the same errors under the same fields
  both ways, the thank-you, the submission in the panel, the letter (or `notify_error` says why);
  with a queue, `queued`, then `delivered` once a worker has sent it.
- Handlers: a `handled` or `handler_error` line per handler in the log (card or `inbox_get`); channels: `inbox_get` → `channels`, a row per recipient, `failed` carries the error.
- `php artisan webx:inbox:prune --dry-run` — what the configured ages would remove;
  `tests/PerformanceTest.php` — the query budget of the panel's list and of a submission.
- With MCP: `inbox_forms_list` — a switched-on form says `notifies: true` unless read only in the panel;
  the writing tools take `dry_run: true`.

## Read more

- [README.md](README.md) in this directory — the public door, antispam, attachments, letters.
- Guides: https://webx-ui.github.io/webx-ui/guide/inbox, extending https://webx-ui.github.io/webx-ui/guide/extending
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_INBOX.md
