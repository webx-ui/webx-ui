---
'@webx-ui/php': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': minor
---

The panel on the site, first stage (SB1 of `WEBX_UI_SITE_BAR.md`). An administrator signed in to the panel gets a tab at the right edge of the site's own pages: it opens a 380px drawer (the whole width below 640px) with the panel in a frame — what record the page is, its status, a draft somebody left, and «Open in panel». A visitor gets the page byte for byte as before. `webx-ui/module-admin`: a `RequestHandled` listener puts one `<script>` before `</body>` only for an active administrator of the `cms` guard who may open the record behind the address, and only then sends `Cache-Control: private, no-store` and `Vary: Cookie`; the script is served at `/cms/_bar/loader.{hash}.js`, the drawer is `/cms/_bar` (the shell, `X-Frame-Options: SAMEORIGIN`), and `GET /api/cms/site-bar/context?url=` names the record. `SiteBar::entity()` registers a record type with its panel address and permission; every section with addresses registers its own. `webx-admin.site_bar.enabled` (`WEBX_SITE_BAR`) switches it off for the site. `webx-ui/module-auth`: the `site_bar` column on `cms_users`, `PUT /api/cms/auth/site-bar`, and `siteBar` in the session. `@webx-ui/module-admin`: the `/_bar` route drawn without navigation. `@webx-ui/module-auth`: «Panel on the site» in the user menu.

**Upgrading.** Run the migrations (`php artisan migrate`). A CDN in front of the site should pass requests that carry the session cookie straight through.
