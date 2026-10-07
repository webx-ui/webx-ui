---
'@webx-ui/php': minor
---

An entity with no SEO card names its own page: `HasSeoFallback` (title, lead, picture) answers through a new `FallbackSource` between the card and the site defaults, so the title template, `og:title`, `og:description` and the entity's own `og:image` reach recipes, events, press outlets, services, vacancies, pages, articles, products and brands — the views no longer print `<title>` by hand, and `@webxSeo(fallback: [...])` names a page that is a route. The title template leaves a title that already names the site as written, and the home page without a title is called by the site's name. `/index.php/<path>` is folded into the normalisation 301, and the self canonical never carries the front controller. Organization profiles take only web addresses. `settings_set` clears any field type with `null`.
