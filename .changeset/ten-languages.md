---
'@webx-ui/php': minor
---

Seven more languages in the panel: German, Polish, French, Spanish, Italian, Portuguese and
Turkish, alongside the English, Russian and Ukrainian that were already there. A site still
decides which of them to offer in `config('webx-localization.panel')`.

A test in each package holds the ten key sets together — a missing line falls back to English
rather than to a key, which is right and also the reason a gap can sit unnoticed.
