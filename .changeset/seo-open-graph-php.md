---
'@webx-ui/php': minor
---

`module-seo`: the head prints the whole Open Graph and Twitter set from what the page already has — `og:locale` and its alternates, `og:type` per entity (`HasOpenGraph`: blog articles and recipes are `article`, with `article:published_time`, `:modified_time`, `:section`, `:tag`), `og:image` with its type, size and alt (an SVG falls through to the next picture; with `module-media` a 1200×630 variant), mirrored `twitter:*` and `twitter:site` from the organisation's X profile. New switch `print.article`; the card's share fields are hidden unless `WEBX_SEO_OG_FIELDS=true`. `seo_test_url` reports the lines as `social`.
