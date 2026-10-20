---
'@webx-ui/module-admin': patch
'@webx-ui/php': patch
---

The author of a note and of a line of the journal (`WxNotes`, `wx-history` — a submission's notes,
a code's history) is drawn with their photograph rather than initials. `webx-ui/module-admin` sends
the key of the photograph beside the name (`author.avatar`, `admin.avatar`), read for the whole
feed in one query (`Authors::people()`, `HistoryPresenter::page()`); the panel resolves the keys
through its library in one batch (`useAvatars`). Initials stay for nobody with a photograph, a panel
without a library and an account deleted since, which keeps the name it signed with.
