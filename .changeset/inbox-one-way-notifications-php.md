---
'@webx-ui/php': minor
---

A form of the inbox tells one way: `options.notify_via` — `email` (the letter to its addresses) or
`channels` (the channels of the notifications module), never both; the settings of the other way
are kept. A migration writes the way of every existing form: `channels` when it has channels
chosen, `email` otherwise. A form set to channels on a site without the notifications module
writes its letter instead, and `webx:doctor` warns about it. The audit and `notifies` read the way
the form is set to, and so does sending again (the panel's button, `inbox_notify`): the letter, or the
channels' failed rows. `inbox_form_get` and the form's API return `notify_via`.

`options.redirect` is a link of the panel (`Links\Link`, the menu's shape): a record of the site
opens in the language the submission came in, or in the main one when it has no translation; a
path gets the visitor's language prefix; nothing is the thank-you in place of the form. The same
migration turns a saved address into an «Address» link, so it keeps going where it went.

A new field without a name is named after its label (`Fields\FieldNames`) in the panel's API and
in `inbox_form_save`. Renaming a field takes the form's e-mail field with it, and the export keeps
older answers in the field's column (by id, not by the name they arrived under).

The channels' relation target answers a `mark` («Off») for a switched-off channel.
