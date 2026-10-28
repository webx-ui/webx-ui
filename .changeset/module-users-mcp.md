---
'@webx-ui/php': minor
---

`webx-ui/module-users`: «Customers» for an agent — `users_list`, `users_get`, `users_create`, `users_update`, `users_delete`, `users_send_password_reset`, `users_resend_verification`, `users_unlink_provider`, `users_groups_list|save|delete`, `users_settings_get|set` behind `users:read` / `users:write` (the settings behind `users.settings`), and the resource `users://catalog`. They go through the panel's own classes, take `dry_run` (rolled back, no letters) and never answer a password, a link, a token or a secret of a network.
