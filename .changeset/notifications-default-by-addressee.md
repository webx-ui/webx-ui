---
'@webx-ui/php': minor
'@webx-ui/module-notifications': minor
---

Notifications: the default template of a channel follows its addressee. An event may declare `addressed` beside `sample` — a default template by addressee variable, then by provider — and a channel whose recipients name that addressee (`{"variable": "customer_email"}`) starts with it, gets it back with `default_template: true` and in the panel's «Insert the default template»; any other channel, and an event without `addressed`, gets `sample` as before. `GET meta` and `notifications://events` give `addressed` by language like `sample`; `notifications_default_template` takes `addressee`. In the panel a channel not saved yet swaps an untouched default template when its addressee changes. Orders: `orders.placed` declares the buyer's letter for `customer_email` — a new channel to the buyer gets «Order accepted», the shop's channel «New order». Saved channels are not changed.
