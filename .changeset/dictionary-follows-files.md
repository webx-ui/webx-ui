---
'@webx-ui/php': patch
---

The panel's cached dictionary follows its lang files: the cache key carries how many there are and when the newest changed, so new words reach the panel on the next load instead of after a day or `webx:locales:clear`.
