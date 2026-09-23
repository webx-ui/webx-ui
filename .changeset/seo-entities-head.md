---
'@webx-ui/php': minor
---

hreflang, breadcrumbs and schema.org for entities, and the sitemap in the panel.

- `webx-ui/module-seo`: the `<head>` of a page now prints `<link rel="alternate" hreflang>` for
  every language the entity is visible and open to the index in, plus `x-default` (only with the
  language in the path), a `BreadcrumbList`, the entity's own schema.org blocks and a
  `twitter:card`. Each line of the sitemap carries the same `hreflang` set. New contracts
  `HasBreadcrumbs` (with `Crumb`) and `HasStructuredData`; `Seo::push()` for JSON-LD that belongs
  to the response rather than the entity. The trail starts at the site's home, named by the new
  `seo.home-crumb` setting (per language, "Home" from the dictionary until written).
  `<x-webx-seo::breadcrumbs :for="$entity" />` prints the visible crumbs from the same list.
  `webx-seo.print` gains `hreflang`, `breadcrumbs`, `structured_data` and `twitter`.
  `GET`/`POST /api/cms/seo/sitemap` and the MCP tool `seo_sitemap_status` report the files, the
  counts, when the map was built and how many visible addresses were left out and why;
  `test-url` says whether an address is in the map and why not.
- `webx-ui/module-pages`: `Page` implements `HasBreadcrumbs` — the pages above it, an unpublished
  one left out. The fallback view prints the crumbs.
- `webx-ui/module-blog`: `Article` (feed → main rubric → article, a `BlogPosting`), `Rubric` and
  `Tag` (feed → it) implement the contracts; a rubric page pushes an `ItemList` of its articles.
  The fallback views print the crumbs; the article's rubric link above the title is now its trail.
