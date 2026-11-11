---
'@webx-ui/php': minor
---

Orders: the agent reads and edits the answers to the extra fields. `orders_get` gives `fields`, the order's snapshot whole (`key`, `type`, `label`, `value`, `text`); `orders_list` gives none of it. `orders_update` takes `fields: {key: value | null}` by the rules of the card's edit, its dry run answers `fields_changed` — each row added, changed or taken away with its text before and after — and keeps nothing; a key neither on the order nor of a field switched on is a refusal that names it. `orders_checkout_preview` has a block `fields` — each field of the steps shown with its place, `required`, `shown` and `filled`, never the answer; `orders_checkout_steps` gives each step its `field_places` with how many fields each may ask, the step `comment` says when it is shown for its fields alone, and why it is not shown when it has neither a comment nor a field.
