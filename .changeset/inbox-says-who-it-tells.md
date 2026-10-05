---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
---

A form that would notify nobody now says so instead of looking like a queue that has not run.
`module-inbox` reports, beside the stored `options.recipients`, who a submission would actually
be written to: `recipients` (each with `receives` and a `problem` — `admin_deleted`,
`admin_inactive`, `invalid_email`) and `notifies`, in the panel API and in the MCP tools
`inbox_forms_list` / `inbox_form_get`. A submission of such a form logs `no_recipients`. The
panel warns in the form editor and marks the form in the column; the editor opens on the tab
named by `?tab=`. With `webx-ui/module-audit` installed, the check `inbox.no_recipients`
(warning) lists switched-on forms that would notify nobody, with a link to their Notifications
tab. The stored option shape is unchanged.
