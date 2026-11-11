---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
---

Inbox: steps of a form (FORM_STEPS §3). A row of type `step` in the list of fields starts a step —
title, short label, line under it, the words of «Next», a condition of its own; switched off, it takes
its fields with it. The site prints a form of steps through the widgets' `<x-webx-form steps>` and
`<x-webx-form.step>`, and «Next» asks the new door `POST {prefix}/{slug}/check` (204 or a 422, nothing
taken). A flat form is printed as before; a site's own `form.blade.php` without `$steps` gets the whole
form on one page, and the audit's `inbox.steps_in_old_view` says so. The panel's list of fields draws
step rows as headings with «Step» to add one; `inbox_form_get` returns `steps`, `inbox_form_save`
refuses an empty step and more than ten, shows the steps in `dry_run` and takes `order`.
