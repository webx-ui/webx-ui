---
'@webx-ui/php': patch
---

`webx-ui/module-blog`: the page of an author asks whether they have something published once a
request, not once per part of the page that wants to know (the handler, `robots`, every `hreflang`
line and switcher link); the sitemap reads it with its list of authors (`withExists`).
`webx-ui/module-seo` and `webx-ui/widgets`: `hreflang` and the language switcher read an entity's
addresses through `RouteCache`, like `url()` — none from the registry on a warm cache.
