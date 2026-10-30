---
'@webx-ui/module-admin': minor
'@webx-ui/module-inbox': patch
'@webx-ui/module-orders': minor
'@webx-ui/php': minor
---

The notes of a record have one look across the panel: `WxNotesList` (`@webx-ui/module-admin`) draws the feed — the author with the time, the empty state, the box and «Add a note» — and asks no server anything: the notes come in a prop, a new one goes out as `add`, the box is `v-model:draft`. `WxNotes` keeps the notes in `cms_notes` as before and now draws them through it; the inbox looks and works as it did. New type `NoteEntry`.

The card of an order has «Notes» under its tabs (node `order-notes`, type `wx-orders-notes`): the lines of the order's history of the kind `note` — a manager's from the panel, an agent's `orders_note`, a module's `Orders::note()` — each with its author, and the box for whoever holds `orders.manage`. `POST {api}/orders/{id}/notes` writes one through `Orders::note()` and answers the card; the card carries `order.notes` and `actions.note`. A note never changes once written. The pencil beside the status is a bare icon in the colour of a link, with a tint of the accent on hover and a visible focus.
