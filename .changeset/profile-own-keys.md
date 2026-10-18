---
'@webx-ui/php': minor
'@webx-ui/module-auth': minor
'@webx-ui/module-admin': patch
---

The profile and your own keys (WEBX_UI_PERMISSIONS.md §8, §21).

`webx-ui/module-auth`:

- **`PUT auth/me` refuses what is not a profile field.** `name`, `avatar`, `locale`, `password`, `current_password` are taken; any other field (`roles`, `is_super`, `is_active`, `email`, …) is now a **422 under its own name** instead of being dropped silently. A password changed from the profile is a row of the account's history, as from the administrators form.
- **A new sign-in address is proved at that address.** `POST auth/me/email` (`email`, `current_password`) sends a letter with a signed link and changes nothing; `GET auth/me/email/confirm` — the link — moves the address once (every other link in flight dies with the old address) and lands in the panel with `?email-change=done|expired|taken`. Offered only where mail leaves the site (any transport but `log`/`array`, or a local machine); `webx-auth.email_change` / `WEBX_AUTH_EMAIL_CHANGE` settles it either way, and `me` reports it as `emailChange`. The letter is the publishable view `webx-auth::mail.email-change`.
- **`GET auth/keys?mine=1`** lists only the caller's keys, even for a holder of `admins.manage`.

`@webx-ui/module-auth`:

- **The «Your profile» dialog has sections**: profile, interface (language, theme, panel on the site — applied at once), password (the current one first), email (a link to the new address; where the site sends no mail, it says who changes it). A row leads to «Connect an agent» for agents and keys.
- **«Connect an agent» lists and issues your own keys** — for an administrator without `admins.*`, who never sees Administrators → Keys. `WxKeyList` and the key dialog take `mine`: no owner to choose and no list of people fetched.
- `AuthSession.requestEmailChange()`; the plugin says how a confirmation link went when the panel opens on it.

`@webx-ui/module-admin`: the drawer of a narrow panel closes on any change of page, not only on a pick from its menu — a link from the profile dialog in its foot no longer lands behind it.
