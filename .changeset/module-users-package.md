---
'@webx-ui/php': minor
---

New package `webx-ui/module-users` — visitors of the site, apart from the administrators: the table `users` (over Laravel's empty table of an older skeleton it makes its own; over one with rows it refuses in words, and `webx:doctor` says the same), the guard `site` with a provider that lets no blocked visitor in, the sign-in, the registration and the sign-out at `/account/*` on the forms of `widgets` (one answer for every wrong pair, «Remember me», limits that answer 429 with the seconds), and `UserVisitors` behind the panel's `Visitors`. The skeleton `webx-ui/site` no longer creates Laravel's `users`, `password_reset_tokens` and `App\Models\User`. Theme default: the composition `layout.account` and `<x-layout.account>`. Widgets: a form sent in place shows why a 429 refused it instead of «could not send».
