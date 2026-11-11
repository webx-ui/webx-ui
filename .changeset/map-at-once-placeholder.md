---
'@webx-ui/php': minor
---

The `map` block gets the switch "Show the map without consent" (`at_once`, off by default and for blocks saved before it), with a hint in all 10 languages: the map hands the visitor's IP address to the map service and may set cookies. On, the block's map does not wait for consent to `media` whatever the site says; off, the site's "Maps without asking" decides, as before. A Google "Embed a map" that does not wait — this switch, `:consent="false"` or the site's setting — has its `<iframe>` printed by the server, in the page's HTML at once and without JavaScript. The notice before consent of every map is now a card on the theme's surface over a drawing of streets in the theme's colours: no picture and no request before consent. Sites take the new block type with `webx:blocks:import map --path=vendor/webx-ui/widgets/resources/blocks --replace=map --publish`.
