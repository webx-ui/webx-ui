---
'@webx-ui/php': patch
---

`Locales::content()` — the site's content language for a panel request: the one asked for, the
request's, or the site's main one, whichever the site has. The link picker and the relation fields
work in it too, so a panel read in a language the site lacks no longer finds nothing to link to.
