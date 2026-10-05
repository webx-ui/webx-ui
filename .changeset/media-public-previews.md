---
'@webx-ui/php': patch
---

A library picture's `thumb` on the site is now the address of its preview on the disk, cut on first
use. It used to be the panel's preview route, which needs the panel's sign-in, so the recipe and
event galleries showed every visitor a 401 in place of each picture.
