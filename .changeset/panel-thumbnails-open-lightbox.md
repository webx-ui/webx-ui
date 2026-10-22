---
'@webx-ui/module-admin': minor
'@webx-ui/module-audit': patch
'@webx-ui/module-banners': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-catalog': patch
'@webx-ui/module-catalog-properties': patch
'@webx-ui/module-events': patch
'@webx-ui/module-media': patch
'@webx-ui/module-recipes': patch
'@webx-ui/module-seo': patch
'@webx-ui/module-services': patch
'@webx-ui/php': patch
---

Every picture the panel shows small opens large: a click on it opens `WxLightbox` over the whole set it stands in, on the full picture and in the panel's language. `@webx-ui/module-admin` gains `WxPreviewThumb` (a thumbnail that is a button opening the gallery, keeping its click from the row) and `useLightbox()` (`open`, `labels`, and `click`, which leaves Ctrl and the middle button to a link); the words move from `webx-media::lightbox.*` to `webx-admin::lightbox.*` and gain `play` and `view`. Wired in: the product gallery (with its videos), the gallery and file fields and the picture field of forms — whose picture now opens large, swapping it is a button of the bar — the library (its double click and «View»), the covers of the lists of products, articles, events, services and recipes, the banners of a place, the pictures of property values and their dialog, the share picture of the SEO card and the pictures of an audited page. The panel rows of events and recipes carry `cover.url`, and those of banners `image`, for the full picture.
