---
'@webx-ui/php': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-seo': minor
---

The panel on the site, second stage (SB2 of `WEBX_UI_SITE_BAR.md`): an SEO tab. It shows the title, description, H1 and `noindex` of the page beside it, each with what the page prints and where that came from (a rule, the record, its name, the site defaults), a search-result preview with length counters, and «All fields in panel» for the rest. A field that a rule sets above the record is flagged before saving, with a link to the rule. `webx-ui/module-seo`: the `seo` section of the bar (any address for `seo.manage`, a record's page for whoever may save its card), `GET /api/cms/seo/site-bar?url=&locale=`, and `PUT /api/cms/seo/entity/{type}/{id}` — the named fields of a record's card laid over what it has, behind the permission of the record's section. An address without a record is edited in an exact rule through `seo/urls`; under a mask the new rule starts as its copy. A page without a record now gets the bar for an administrator with `seo.manage`. `webx-ui/module-admin`: the script on the page sends `<html lang>` to the drawer and puts a saved title into `document.title`. `@webx-ui/module-admin`: `siteBarTabs` on `AdminModule` — a module's own tab in the drawer, drawn where the server lists it. `@webx-ui/module-seo`: the tab itself, and `?rule={id}` opens that rule on the rules screen.
