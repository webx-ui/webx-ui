---
'@webx-ui/module-admin': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-events': patch
'@webx-ui/module-recipes': patch
'@webx-ui/module-services': patch
'@webx-ui/module-vacancies': patch
'@webx-ui/php': patch
---

Panel lists show an address instead of «No address in this language». They are read in the site's
content language, not the panel's, so an English-only site in a Russian panel shows its addresses
plainly; and a record with no address in the language a multilingual list is read in shows its
address in the site's main language, with an info mark whose tooltip says so (`address_locale` in
the rows, `PanelAddress` in `routing`, `WxAddressNote` in `module-admin`). Pages, articles,
events, recipes, services, vacancies and every module's categories.
