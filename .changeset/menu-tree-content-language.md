---
'@webx-ui/php': patch
---

A menu read in a panel whose language the site does not have shows where its items lead again:
the tree is worked out in the site's content language — the one asked for, the request's, or the
site's main one — instead of the panel's. An English-only site in a Russian panel listed every
item as leading nowhere and «not on the site», and an item titled only in English as untitled.
