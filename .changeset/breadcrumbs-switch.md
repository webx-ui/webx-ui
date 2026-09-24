---
'@webx-ui/php': minor
---

Pages, blog and services each get a `breadcrumbs` switch (`WEBX_PAGES_BREADCRUMBS`, `WEBX_BLOG_BREADCRUMBS`, `WEBX_SERVICES_BREADCRUMBS`, on by default): off, the package views stop printing the visible trail, while the `BreadcrumbList` in the head stays. Services also get `index` (`WEBX_SERVICES_INDEX`, on by default): off, the package no longer answers its prefix, so a page of blocks can take `/services`, and the services' breadcrumbs start with whatever stands there.
