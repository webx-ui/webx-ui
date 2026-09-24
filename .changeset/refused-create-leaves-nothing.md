---
'@webx-ui/php': patch
---

`services_create`, `articles_create` and `pages_create` write the row and its values in one transaction: a value the screen refuses no longer leaves a bare entity behind with its address taken.
