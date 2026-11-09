---
'@webx-ui/php': minor
'@webx-ui/module-banners': minor
---

Banners, tariffs and product collections: the button of a banner (and its «Button» layer), the button of a tariff and the «All products» link of a collection take a file of the media library — a brochure, a price list — with «Download instead of opening» and «Open in a new tab» (`props.files: true` on their `wx-link`). The cards carry `download` (the name the file was uploaded under, or null) beside `url`, `new_tab` and `rel`; the banner views, the tariffs block and the collection view print the `<a>` with `LinkAttributes::of()`. A file key the library does not have is refused when it is new — in the panel, through the model and in an agent's `dry_run` — and a file deleted since only takes the button off the site. Every link saved before prints as it did; the files of a list of banners or tariffs are one query.

Links: `LinkUrls::candidates()` asks the library for the files of a whole set of links at once.

Tariffs: a site's own copy of the `tariffs` block type keeps its old template — a file opens rather than downloads — until `webx:blocks:import tariffs --path=vendor/webx-ui/module-tariffs/resources/blocks --replace=tariffs --publish`.

Banners: in a slider that fades, the layers of a slide faded out no longer take the click meant for a button of the slide shown — the slide's `pointer-events: none` did not reach a layer that says `auto`.
