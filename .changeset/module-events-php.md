---
'@webx-ui/php': minor
---

New `webx-ui/module-events`: workshops, meetings and webinars with a date, a place, a price and a
link to book, each a page of fixed structure. Flat categories with pages of their own, related
services, the events to come on the index and the category pages with the past ones kept at their
addresses, `events()` for templates, an `.ics` file per event, schema.org `Event`, and "Duplicate"
for the next event of a series. `module-seo` patches its card onto the two new screens, and
`webx:setup` and `webx:doctor` know the package.
