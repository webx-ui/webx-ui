---
'@webx-ui/php': patch
'@webx-ui/module-audit': patch
---

«Content searched» on the audit overview has a «What this means» popover: besides the crawl, the
audit reads the text modules keep in the database; green modules hand it over, orange ones are
installed but not searched yet — their pages are still crawled, their drafts and hidden fields are
not, and a fix cannot reach them.
