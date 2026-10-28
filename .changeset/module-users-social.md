---
'@webx-ui/php': minor
---

`webx-ui/module-users`: sign-in through social networks — Google and Facebook on `laravel/socialite`, and any other registered with `SocialProviders::register()`. The keys are kept in `users_providers` (the secret encrypted, never given back), with `.env` as the fallback; a provider without its keys has no button and no route. A verified Google address on an account with a confirmed address links by itself and tells the owner by letter; anything less links once the visitor signs in with the password. «Almost done» asks for what the network did not give, the account links and unlinks networks in «Sign-in and security», and the last way to sign in is never unlinked. `webx-ui/module-admin`: the shared `WebxUi\Admin\Support\Field` (with `required`) for a provider's settings; `webx-ui/module-notifications` uses it in place of its own `Sending\Field`.
