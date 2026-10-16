---
'@webx-ui/php': minor
---

New package `webx-ui/module-snippets`, its PHP core: codes of outside services (a tag manager, a counter, a chat) in the places `head`, `body_start`, `body_end` of theme-default's layout and in places of `config/webx-snippets.php`, printed by `<x-webx-snippets place="…" />` with coverage by paths, languages, environment and cookie consent; a section behind the permission `snippets.manage`, saved straight to the site, with the whole code before and after in the journal; lost and undeclared places in `webx:theme:check` and `webx:doctor`. Around it: `RecordsHistory::historyKeepsFull()` in module-admin, `Contracts\ThemeCheckPart` in themes, the three places in theme-default, and in widgets `Widgets::EARLY` for Consent Mode's default, `consentReady()`, and scripts of a consent template that keep their type and run in order.
