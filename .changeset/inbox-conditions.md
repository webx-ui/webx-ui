---
'@webx-ui/php': minor
---

Inbox and widgets: a field can be shown only for some answers above it. `options.when` / `options.unless` on an inbox field — `{ "purpose": ["agency", "pre-register"] }`, "or" inside a list, "and" between names — name a select, radio, checkbox or consent standing earlier. The server decides: a hidden field is not checked, not kept and not in the hash of duplicates, with JavaScript or without. On the site `<x-webx-field :when :unless :condition>` prints the condition, and the widgets' `form.js` hides the field (`hidden`, controls `disabled`) as the answers change; without JavaScript every field is shown with a line saying when it applies, and a required one has no browser `required`. The panel and `inbox_form_save` refuse a condition naming an unknown, later or non-choice field or a value it does not offer, refuse deleting or dragging below a field others count on, and carry the conditions along when it is renamed.
