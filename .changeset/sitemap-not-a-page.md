---
'@webx-ui/php': minor
'@webx-ui/module-seo': minor
---

A route handler can now say it never shows a page: `WebxUi\Routing\Contracts\NotAPage`, a marker for
the class a site binds over a module's handler when every address of that type redirects (events
sent to an external booking page, categories that are only a filter). The sitemap leaves such a type
out whole: no file, no line in the index, `test-url` answers `not-a-page`, and the status (the panel
card and `seo_sitemap_status`) lists it under `excluded_types` with the handler and its number of
addresses. The cached map is keyed by the set of those types, so a deploy that changes the binding is
picked up without a rebuild. `RouteType::servesPages()` answers the same question for anything else
that lists pages. Types without the marker behave as before.
