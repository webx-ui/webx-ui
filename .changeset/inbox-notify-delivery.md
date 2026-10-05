---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
---

A submission now says whether its notification left, not only whether it was handed to the queue.
On a site with a queue the letter is marked `queued` until a worker has sent it (`notified_at`, a
`notified` line with the count) or given up on it (`notify_error` with the reason, a
`notify_failed` line with the address); each recipient is listed with how its letter went. On
`sync` nothing changes. The panel's submission screen, `inbox_get` and `inbox_list` show the state
(`none`, `queued`, `delivered`, `failed`); an administrator can send the notification again from
the submission's menu, through `POST …/submissions/{id}/notify` or with the new MCP tool
`inbox_notify` (`dry_run` first). With the site audit installed, the check `inbox.notification`
lists letters that failed or have been queued for long — a sign the mail settings are wrong or no
queue worker runs. Run the migrations: two nullable columns are added to `inbox_submissions`.
