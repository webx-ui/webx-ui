---
'@webx-ui/php': minor
---

`webx-ui/widgets`: `<x-webx-language-switcher>` — a dropdown or a row of the site's languages,
each named in itself, leading to the same page in that language (its address from the
`webx-ui/routing` registry, as `hreflang`) or to that language's home page, marked
`is-fallback`, where there is no translation; nothing on a site with one language. A stylesheet
of its own only. `theme-default` puts it last among the header's actions on a multilingual site,
and the kitchen sink has a page for it.
