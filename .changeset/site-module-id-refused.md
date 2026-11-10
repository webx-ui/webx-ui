---
'@webx-ui/php': patch
---

`module-admin`: `webx:module:add` refuses a package whose id one of the site's own modules (`modules/<name>`) already goes by — before Composer when the id is known (the catalogue's, an installed package's), right after it otherwise, with `composer remove <package>` as the way back.
