---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
---

Forms: the summary of what was chosen (FORM_STEPS §7). The widgets' `<x-webx-form.summary :fields>`
repeats the answers given so far — the labels of the fields and the words of the choices — each with
«Edit» that brings the field's step back and focuses the field (`summary.js`, claimed only by the
summary; hidden without JavaScript). Inbox: a row of type `summary` (title, line under it,
`options.fields`) prints it in a form of steps; it is never answered and nothing of it goes into a
submission, a rename carries into it. The panel's field dialog has the type «Summary» with «What to
show»; `inbox_form_save` takes it.
