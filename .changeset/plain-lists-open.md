---
'@webx-ui/core': minor
---

`WxSelect`: a dropdown for one value or several, wrapping Reka UI's Combobox — which brings the
keyboard behaviour, the floating positioning and the ARIA wiring.

The model holds the option's value and `null` when nothing is picked; with `multiple` it holds an
array that is empty rather than null, so the shape a backend receives never changes with the
selection. `filterable` adds a search field, and the `search` event carries every keystroke for
lists that live on the server. Selected values render as removable tags in multiple mode, and the
list is teleported so it escapes a card's `overflow: hidden`.

Clicking the field opens the list — the underlying combobox does not do that by default, which is
not what anyone expects from a select.
