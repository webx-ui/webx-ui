---
'@webx-ui/php': minor
---

The search box in the pages section looks in every language the site has.

A list shows the title a page carries, not the one the reader asked for: a page named in English
alone is drawn with that English name in a Russian panel. A search that looked at the current
language only could not find what was on the screen, and said so with an empty list — which reads
as a broken box rather than as an answer.

`webx-ui/localization` gained `whereTranslationLikeAny()` for it, which walks the site's languages.
The record's own keys cannot be walked instead: asking the database about them means reading the
column as text, and in the stored JSON a translation is escaped (`Д…`), so a term in anything
but ASCII would never match. `pages_tree` follows the panel — its `locale` argument says which
language to answer in, not which one to look in.
