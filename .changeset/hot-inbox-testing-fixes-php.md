---
'@webx-ui/php': patch
---

`module-inbox`, from a full test of v0.63.0:

- **Antispam.** A `webx_ts` that does not decrypt is refused like a missing one. A stale mark still passes, and `APP_PREVIOUS_KEYS` covers a key rotated under a cached page. Both 429s, the form's limit and the route's, answer with `webx-inbox::errors.too-many` in the page's language.
- **Export.** A cell starting with `=`, `+`, `-`, `@`, a tab or a CR gets a leading apostrophe, so a spreadsheet does not run it as a formula. Status titles are in the panel's language. A consent is stored as one stable key (`yes`) whoever ticked it, and is put into words when shown.
- **Corrections.** `PUT submissions/{id}` with `values` checks each answer against its snapshot type: e-mail, `Y-m-d` date, tel, or a choice by value or label, with the payload following the choice. Files and consents are refused, and so is an unknown name. Nothing in the request is written unless all of it passes. Every change writes a `value` line in the log with the field (new nullable `inbox_submission_events.field`), the old text, the new text and the admin.
- **List filters.** A bad `from` or `to` (strict `Y-m-d`), an unknown `view` or a non-numeric `assignee` is a 422 in the panel list and export, not a 500 or a quiet zero. `inbox_list` refuses an unknown view, sort or date by name and lists the valid ones, and takes an assignee by email.
- **`inbox_form_save`.**
  - Each field entry is planned against what the entries before it leave. Removing and re-adding a name in one call works, and duplicate names in one call are refused.
  - Refusals name the entry, and a wrong type lists the valid types.
  - A select, radio or checkbox needs at least one choice, in the panel too. Plain strings are accepted as choices.
  - A recipient `admin_id` without an account is refused when it is added, and the refusal says which entry.
  - `options.email_field` must be an e-mail field of the form.
  - The `form` argument's description says it can be left out again.
- **MCP answers.** `inbox_get` drops the always-null `previous_id`/`next_id`, gives notes the same `{id, name}` author as the log, and gives the status by key. `inbox_form_get` lists statuses by key. A form with no `in_table` field shows its first two short answers as columns, and an empty `values` is `{}`.
- **Mail and prune.** A queued letter about a submission deleted before the worker ran is dropped instead of landing in `failed_jobs`. `notify_queued` is logged only for letters that were actually pushed. `webx:inbox:prune` refuses a `--days` or `--spam-days` that is not a whole number.
