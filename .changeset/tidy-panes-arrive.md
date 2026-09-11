---
'@webx-ui/core': minor
---

`WxListDetail` — the screen an admin panel keeps coming back to, as a component: something to narrow
the set, the records, and the open one.

Inbox and messages, orders and an order, tickets, users, invoices — the furniture is identical every
time, and so is the part nobody enjoys writing twice: what happens at 900px. It is layout only. Rows,
records and filters stay yours, in the `filters`, `list`, `detail` and `empty` slots.

`v-model:open` is one idea doing two jobs: beside the list it picks the detail over the empty state,
and on a screen too narrow for a third column it raises the record as a panel — with `back` handed to
the slot, so the column and the screen are written once. Which record is open stays with the caller;
the component only knows whether there is one.

The thresholds are not breakpoints but arithmetic on the widths you gave: the filters column folds
away below `filtersWidth + listWidth + detailMin`, the detail below `listWidth + detailMin`. And they
are measured against the component's own width, so a sidebar collapsing to a rail hands the screen
160px and the filters column comes back by itself, while the same screen in a 700px drawer behaves
like the phone it effectively is. Widen the list and both thresholds move with it.

When the filters column does not fit, that slot moves into a drawer and the `list` slot is handed the
button to open it.
