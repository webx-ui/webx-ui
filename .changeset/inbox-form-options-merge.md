---
'@webx-ui/php': patch
---

module-inbox: `inbox_form_save` merges `options` by key over what the form has, as its description
promised: a key left out keeps its value, a key sent as `null` is taken away, a key sent replaces
its value whole (a value in languages too, like the title sent as a map). A field's `options` are
merged the same way unless its type changes. The panel still saves the settings whole.
