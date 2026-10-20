---
'@webx-ui/php': patch
---

`webx-ui/module-admin`: the "Address" suggestions of a link field offer only pages a visitor opens.
A package marks its machinery where it registers it — the route attribute `SiteRoutes::SERVICE`,
set on a group — and `webx-ui/module-blocks` (previews, block stage, bundles), `webx-ui/module-seo`
(robots.txt, sitemaps and their stylesheet), `webx-ui/module-inbox` (script, form intake) and
`webx-ui/widgets` (form token, showcase) do. `*.xsl` joins the masks of `webx-admin.links.exclude`,
which stay for routes nobody of ours registers.
