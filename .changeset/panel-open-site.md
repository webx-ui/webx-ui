---
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': minor
'@webx-ui/php': minor
---

The panel leads to its site. The name in the head of the sidebar is a link with a small ↗ after it, the rail has a ↗ under the button that opens the sidebar (tooltip "Open the site"), the head of the phone's drawer leads there too, and the account menu has an "Open the site" entry — all in a new tab. The manifest carries `siteUrl`, the home page in the site's main language rather than the panel's (with the language prefix `webx-ui/routing` gives it), and the context exposes it as `siteUrl`; `createAdmin({ siteLink })` sends the link elsewhere, or with `false` leaves it out everywhere. The word `nav.open-site` is in all ten languages.
