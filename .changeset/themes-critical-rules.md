---
'@webx-ui/php': patch
---

themes: `webx:theme:critical` keeps every rule the first screen needs and the cascade of every page. A selector list is cut only at its top-level commas, so `.site:has(.a, .b) .header` and `[title="a,b"]` are no longer dropped; and the rules of all pages are merged in an order that keeps each page's own, so one page's block bundle no longer lands after another's and beats its modifiers. Rebuild the critical CSS of a site: `php artisan webx:theme:critical && php artisan webx:theme:critical --check`.
