---
'@webx-ui/php': minor
---

`webx-ui/module-services`: a catalogue of services — services made of blocks with a draft and a
history, flat categories that are pages of the site, both on one level under one prefix, an index
route, two orders (the whole list and each category), breadcrumbs through the main category, a
schema.org `Service` naming the site's `Organization` as provider, and fields of the project in
`extra`. The panel's API and screens follow.

Alongside it: a refused address now names whoever holds it (`routing`); the site's `Organization`
block carries an `@id` other blocks can point at (`module-seo`), which also patches its SEO card
onto the two new screens; `wx-slug` is a shared slug field type in `module-admin`, and `services`
is in the catalogue `webx:setup` offers; `OneSpellingPerAddress` moves from the blog to
`localization`, since the second module with a list page needs it too.
