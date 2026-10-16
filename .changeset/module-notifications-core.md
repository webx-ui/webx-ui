---
'@webx-ui/php': minor
---

New package `webx-ui/module-notifications`, its PHP core: notification channels of a provider — Telegram (HTML templates checked against Telegram's tags, "Find chats" from the bot's updates) and e-mail (the site's mail or the channel's own SMTP) — with encrypted secrets that no answer returns, recipients fixed and from the event (an addressee such as the customer's e-mail), and a template in every language of the site with `{{variables}}` escaped by the provider. Modules declare events in `NotificationEvents` without requiring the package and call `Notifications::send()`: a delivery log row per recipient, a queued job per channel with three attempts, or after the response without a worker; tests from the form, retries from the log, `webx:notifications:prune`, and a check in `webx:doctor`. In module-admin, `RecordsHistory::historyMasked()` writes a secret's change to the journal as a mask.
