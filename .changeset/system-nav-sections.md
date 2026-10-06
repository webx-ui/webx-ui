---
'@webx-ui/module-admin': patch
'@webx-ui/php': patch
---

«System» is split under captions: the settings on top, then «Site» (files, blocks, regions,
menus), «Search and checks» (SEO, the audit, a search index) and «Access» (administrators,
connecting an agent). The captions are added to the config at boot, so a site with its own
`webx-admin.php` gets them too; a module joins one with `HasNavSection` and `SystemSections`.
