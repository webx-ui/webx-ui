---
'@webx-ui/php': patch
---

`themes`: `webx:theme:sync` no longer fails `composer update` where the database is out of reach on a site with the `database` cache store — the reset of the `theme` cache group says «the cache store is not reachable from here» and the command goes on.
