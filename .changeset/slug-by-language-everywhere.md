---
'@webx-ui/php': patch
'@webx-ui/module-admin': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-blocks': patch
'@webx-ui/module-inbox': patch
---

Addresses, anchors and codes made of a name are transliterated by the name's language in every module, not only in the catalog: articles, tags, events, pages, recipes, services, vacancies, FAQ anchors, press outlets, role codes and form field names. «Хіт» is `khit` in Ukrainian and `hit` in Russian, never `xit`. The panel's address field follows the title the same way as it is typed (`slugify(title, separator, locale)`, `useDefaultLocale()`). Existing addresses do not change.
