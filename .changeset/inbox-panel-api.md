---
'@webx-ui/php': minor
---

`webx-ui/module-inbox`: the panel's side of the forms, the fields and the statuses (§12).

Reading the section and changing what it asks are two permissions: `inbox.view` opens the
column of forms, because that column is the navigation of the section, and `inbox.manage`
writes a form, a field or a status.

Two things the settings needed saying out loud. Their keys are literal and several have dots
in them, so nothing validates them by name — a rule called `options.thank-you.heading` reads
the dot as a path and the value disappears without an error — and what is saved goes through a
white list instead, which also keeps a `select`'s choices from surviving on a field that is no
longer one. A recipient is the one setting refused rather than dropped: an address nobody will
ever be written to looks exactly like one that works.

A field's machine name is unique among the live fields of its form only, so a name comes back
when the field that held it is deleted; a name with a dot in it is refused, because the intake
would look for a nested array and report the error under a key nothing on the page has. A copy
of a form is switched off and carries the questions and none of the answers.

`GET /inbox/recipients` names the administrators a form can be told to write to — the ones who
may actually open a submission, since a notification is a link and the alternative is a letter
followed by a 403.
