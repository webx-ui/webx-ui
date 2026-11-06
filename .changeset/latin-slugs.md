---
'@webx-ui/php': minor
---

A slug is ASCII on every door. `HasLatinSlug` (`webx-ui/localization`; `HasUrl` brings it) transliterates a slug being written, by the language of the value, on the model: the panel, the exchange, MCP and a plain `save()` all pass through it. A slug that leaves nothing — only hieroglyphs or signs — becomes `<type>-<id>` (`product-123`). `SlugType` passes only `[a-z0-9]` and separators, after that transliteration, and the page, article and tag dialogs now check by it. A saved slug nobody touches keeps its spelling: `php artisan webx:slugs:latin` lists those, and with `--apply` moves them, a 301 through `module-seo` from every address that moved, the ones below in a tree included. `webx:doctor` warns while any are left.
