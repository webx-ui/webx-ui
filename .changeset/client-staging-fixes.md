---
'@webx-ui/php': minor
'@webx-ui/module-media': minor
'@webx-ui/module-inbox': minor
'@webx-ui/module-notifications': minor
---

Fixes from checking a client's site before launch.

- **Site password and the panel.** An administrator signed in to the panel passes the site's password
  (`WEBX_SITE_GATE`) on every address: the page preview and what it loads, a preview reloaded after
  its token ran out, «Open on the site». A stranger still gets the dialog; a switched-off account does
  not pass.
- **Service pages.** The OAuth consent and every route marked `SiteRoutes::SERVICE` get neither the
  panel on the site nor the debug bar, and an SEO rule cannot be made for a service address (panel
  and `seo_urls_set`).
- **Alt of a file.** A library file has a translated alt (card menu → Alt text, `media_set_alt`, read
  by `media_get_file`/`list`/`search`). Templates print it wherever an image field has no alt of its
  own; `hero`, `cards`, `reviews`, `services` and `press-outlets` print alt instead of an empty one.
- **Where the mail goes.** `Mail\MailRoutes` describes the site's mail and a channel's server, knows
  traps (`log`, `array`, a test sandbox, Mailpit/MailHog — `webx-admin.mail.traps`) and a config
  cached before `.env` changed. `webx:doctor` (check `Mail`), the form's Notifications tab, a mail
  channel, the submission card and MCP (`inbox_get`, `notifications_channel_get`, `notifications_test`,
  `notifications_deliveries`) say it; a queued letter says when it leaves and asks about the queue
  after five minutes; a letter a trap took is «test transport», not «sent».
