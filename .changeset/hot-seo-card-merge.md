---
'@webx-ui/php': patch
---

SEO card through MCP `*_update`: a partial `seo` is merged key by key and language by language instead of replacing the card (`MergesEdits` field types in `ScreenValues::patch`); writing the card touches the owner's `updated_at`, and the card is part of the revision of pages, services, recipes, events, articles and vacancies, so a SEO-only edit by somebody else is detected.
