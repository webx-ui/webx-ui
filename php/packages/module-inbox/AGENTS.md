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
  `webx.inbox.submit`) — the `web` group without CSRF, so pages cached whole keep working — and
  the script `{path}/inbox.js` (`webx.inbox.script`).
- **Blade tag** `<x-webx-inbox::form slug="contact" />`, with `:values` for hidden fields by
  machine name and `placement="footer"` (adds `wx-form--footer`, remembered on the submission).
  An unknown or switched-off slug prints nothing.
- **Antispam**, in order: honeypot, timestamp, origin, captcha (reCAPTCHA or Turnstile, only if
  the form asks), rate limit.
- **Attachments** on `config('webx-inbox.disk')`, never in the media library, served only through
  the panel API to `inbox.view`.
- **Letters**: `SubmissionReceived`, queued, `Reply-To` from the form's e-mail field. The
  submission is saved first; a failed letter is recorded as `notify_error`.
- **Event** `SubmissionStored` and the contract `SubmissionHandler` (config `handlers`) for what
  a site does with a submission next.
- **Panel**: one section with its own editor (no described screen); API under `/api/cms/inbox`;
  permissions `inbox.view`, `inbox.update`, `inbox.manage`.
- **MCP** tools `inbox_forms_list`, `inbox_form_get`, `inbox_form_save`, `inbox_list`,
  `inbox_get`, `inbox_set_status`; scopes `inbox:read`, `inbox:write`.
- **Command** `webx:inbox:prune` (`--days`, `--spam-days`, `--dry-run`) — not scheduled by default.
- Also registered: a relation target for forms, notes on submissions, demo content (`resources/demo`).

## Change it without forking

| You want                                  | Do this                                                                                |
| ----------------------------------------- | -------------------------------------------------------------------------------------- |
| A form on a page                          | `<x-webx-inbox::form slug="..." />` in the view, or a block type that prints that tag  |
| Which page or product it came from        | a hidden field on the form, filled with `:values="['product' => ...]"`                 |
| The same form styled per place            | `placement="footer"`, then style `.wx-form--footer` in the site's CSS                  |
| Different markup of the form or a control | `php artisan vendor:publish --tag=webx-inbox-views`; keep `data-webx-*` and `[name]`   |
| Bundle the script yourself                | `php artisan vendor:publish --tag=webx-inbox-assets`, then `WEBX_INBOX_SCRIPT=false`   |
| A reCAPTCHA                               | `WEBX_INBOX_RECAPTCHA_KEY`, `WEBX_INBOX_RECAPTCHA_SECRET`; then turn it on in the form |
| A Turnstile                               | `WEBX_INBOX_TURNSTILE_KEY`, `WEBX_INBOX_TURNSTILE_SECRET`; then turn it on in the form |
| Another intake path or disk               | `WEBX_INBOX_PATH`, `WEBX_INBOX_DISK`                                                   |
| Larger uploads                            | `WEBX_INBOX_MAX_SIZE` (KB); `upload.extensions`, `upload.max_files` in the config      |
| Posts from another domain of the site     | `antispam.origins` in `config/webx-inbox.php` (`--tag=webx-inbox-config`)              |
| Rate limit, duplicate window              | `antispam.throttle`, `duplicate_window` in the config                                  |
| Visitors' IPs not kept whole              | `WEBX_INBOX_ANONYMISE_IP=true`                                                         |
| Forget old submissions                    | `prune.days`, `prune.spam_days`, and schedule `webx:inbox:prune` in the site           |
| A different letter                        | publish the views and rewrite `mail/submission.blade.php`                              |
| Other words                               | `php artisan vendor:publish --tag=webx-inbox-lang`                                     |
| Send submissions to a CRM or mailing list | a `SubmissionHandler` in `handlers` of the config, by slug or `*` — below              |
| Write back to the visitor (a gift, a PDF) | the same: a handler on that form's slug that sends a mailable to its e-mail field      |
| Anything else once a submission is stored | a listener of `WebxUi\Inbox\Events\SubmissionStored`, `ShouldQueue` if it calls out    |

### Send submissions to a CRM

`SubmissionStored` comes once the submission, its answers and files are written (after commit),
for the site's form and one typed in by hand; never for what the antispam stopped. `repeated` is
a double click inside the duplicate window. For "this form goes to that service", name handlers:

```php
// config/webx-inbox.php
'handlers' => ['*' => [App\Inbox\SyncToCrm::class], 'gift' => [App\Inbox\SendGift::class]],

// app/Inbox/SyncToCrm.php — uses Http, WebxUi\Inbox\Contracts\SubmissionHandler, Models\Submission
final class SyncToCrm implements SubmissionHandler
{
    public function handle(Submission $submission): void
    {
        Http::withToken(config('services.crm.token'))->post('https://crm.example/api/subscribers', [
            'email' => $submission->value('email')?->value,
            'group' => config('services.crm.groups.'.$submission->form->slug),
        ])->throw();
    }
}
```

Each handler is a queued job of its own, tried once; throwing is failing and becomes a
`handler_error` line in the submission's log (`handled` on success) — the visitor and the other
handlers never notice. Not run again for `repeated`.

## Do not

- Do not edit anything in `vendor/webx-ui/module-inbox` or copy it into the site. The rows above
  are the supported ways; if none fits, the package is missing a seam — say so.
- Do not write a form in raw HTML posting to the intake: it loses the honeypot, the timestamp,
  the captcha and the `webx_form` marker. Use the tag; rewrite its published views instead.
- Do not drop `webx_form` from a rewritten `form.blade.php`: with two forms on a page, both show
  one form's errors. Keep it, and keep `data-webx-*` so the script still answers without a reload.
- Do not add CSRF to the intake route: a page cached whole carries a stale token and every visitor
  gets a 419 later on. The origin check and the rate limit stand in for it.
- Do not delete a form that has submissions — it is refused. Switch it off instead.
- Do not delete submissions with SQL: rows and files on the disk go together only through
  `webx:inbox:prune`. Run it with `--dry-run` first.
- Do not create submissions through MCP or the database to "test" a form: there is no such tool
  on purpose. Send the form on the site, so the antispam and the letter are tested too.
- Do not ask a form for a captcha the site has no keys for: it refuses every submission and only
  the log says why. Set the keys first.
- Do not hook a CRM into a fork of `SubmitController` or an Eloquent `created` listener: the
  first is the vendor directory, and the second fires before any answer is written. Use the
  handlers or the event.
- Do not run handlers that call slow services on the `sync` queue: they run inside the visitor's
  request. Run a queue worker.

## Check your work

- Send the form on the site, with and without JavaScript: the thank-you appears, the submission is
  in the panel, the letter arrives (or `notify_error` says why).
- With handlers configured: the submission's log (panel card or `inbox_get`) has a `handled` line
  per handler, or `handler_error` with the reason.
- `php artisan webx:inbox:prune --dry-run` — what the configured ages would remove.
- With MCP: `inbox_forms_list`, then `inbox_form_get` for the fields; `inbox_form_save` and
  `inbox_set_status` take `dry_run: true`.

## Read more

- [README.md](README.md) in this directory — the public door, antispam, attachments, letters.
- Guide: https://webx-ui.github.io/webx-ui/guide/inbox
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_INBOX.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
