---
'@webx-ui/php': patch
---

`module-seo`: an exact redirect to itself is refused by the panel's form and by `seo_redirects_set`, and `seo_redirects_set` refuses a regular expression that will not compile, as the form already did — both used to be saved and silently skipped at runtime.
