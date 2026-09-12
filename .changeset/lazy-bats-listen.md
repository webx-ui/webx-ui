---
'@webx-ui/php': minor
---

The panel answers in the language of whoever is reading it. `webx-ui/admin` serves the language
list and the interface dictionary — both public, because the sign-in screen is drawn before
there is a session to ask — and carries `locale`, `locales` and `panelLocales` in the manifest.
`webx-ui/module-auth` stores each administrator's choice on the administrator, so it follows
them to the next machine and so validation messages arrive in the same language as the labels
above them.

Both packages ship English, Russian and Ukrainian. A site adds a language they never shipped by
publishing their `lang` files and translating what is missing; the merge is per line, so an
untranslated key falls back on its own rather than taking its screen with it.
