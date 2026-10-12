---
'@webx-ui/php': minor
'@webx-ui/module-settings': patch
---

The Contacts tab draws only the sections a site uses. `webx-ui/module-settings`: **`webx-settings.contacts.sections`** lists them — `phones`, `emails`, `addresses`, `hours` (with special dates and the time zone), `messengers`, `socials` — and defaults to `['phones', 'emails', 'addresses']`, so an editor is no longer asked about hours and networks the site never prints. A section left out is hidden (`visible: false`, a patch the section lays before the project's), not cut out: a card with nothing drawn is hidden whole, an empty list hides the tab, the keys stay writable from the panel and MCP, and `contacts()` returns whatever is stored. A misspelt section stops the boot with the known names. `@webx-ui/module-settings`: the shortcode key picker does not offer a hidden field.

**Upgrading.** A site that prints the hours or the networks — `<x-webx-hours>`, `<x-webx-socials>`, the chats of `<x-webx-contact-button>`, the header's socials — publishes the config (`php artisan vendor:publish --tag=webx-settings-config`, or adds `'sections'` under `'contacts'` of a config it already published) and lists `hours`, `messengers`, `socials` as it needs. A site that hid the cards with a screen patch (`{"op": "remove", "target": "contacts-hours-card"}`, the same for `contacts-channels-card`) keeps working — the nodes are still there — and can drop those operations.
