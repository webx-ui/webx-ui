---
'@webx-ui/php': minor
---

`webx:snapshot` and `webx:snapshot:restore` move a site's content between stands — local to dev,
dev or production to local — as one `.tar.gz`: the content tables as JSON lines, the public disk
without previews, and a manifest. Every package declares its tables as content, admins, stand,
derived or transient (`SnapshotTables`), so a restore replaces the pages, blocks, menus, settings
and media and leaves the stand's enquiries, journals, tokens and admins where they are. It takes
a `webx:db:backup` dump first, refuses migrations the code does not know and production without
`--force`, rewrites the source stand's addresses, mirrors the files (`--keep-extra` only adds),
reports rows left pointing at nothing, and clears the caches. `webx:doctor` mentions archives left
in storage for more than a week.
