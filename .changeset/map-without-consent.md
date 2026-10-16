---
'@webx-ui/php': minor
---

A site whose owner decided maps need no consent can say so: the switch "Maps without asking" of the Cookie tab (`consent.maps-at-once`) or `webx-widgets.map.consent` set to false (`Consent::mapsWait()`). `<x-webx-map>` then prints no notice and no buttons, no `is-blocked` and no `data-webx-consent="media"` — the banner does not offer media for it — and its script puts the map in at once; `:consent="false"` / `true` decides for one map. The banner and the other categories are unchanged; `widgets.before_consent` reports such maps as one notice with their pages rather than an error. By default maps wait as before.
