---
'@webx-ui/php': minor
---

Widgets: Google Maps as the second provider of `<x-webx-map>` — chosen on the new "Map" tab of the settings (`map.provider` google, `map.google-key`, optional `map.google-map-id`) or in the config (`webx-widgets.map.provider = 'google'`, `map.google.key`). The same frame, notice before consent to `media`, sleep until a click and "Open in maps" (to Google Maps); its own `dist/map-google.js` instead of Leaflet, and nothing of Google on the page before consent. Without a key the map is the place — the address and a link to Google Maps — and the tab warns; a key Google refuses shows the place with "The map could not be loaded". The pin is the package's SVG, the colours follow the site's scheme. Sites that chose nothing keep Leaflet over OpenStreetMap.
