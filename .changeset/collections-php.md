---
'@webx-ui/php': minor
---

A block can show another section's records. A module registers a `CollectionSource` in
`WebxUi\Admin\Collections\CollectionSources`, and a block schema names it in a field of the new
type `wx-collection` (`"props": { "source": "faq" }`). The page keeps only the choice: which
categories, a limit, whether to draw a filter, and whether to print schema.org markup (`null`
means on when no category is chosen). The site reads the records: `items`, `groups` for the filter
and `filter`. One chosen category shows its own order; none or several show the order of the whole
list, each record once. A source that is gone reads as an empty list, so the page stays up.
`GET /api/cms/collections` lists the sources this administrator may place.

A module can also offer block types: `BlockOffers` in `webx-ui/module-blocks` holds the documents
the module ships in `resources/blocks`, and `webx:blocks:offered --install` puts the missing ones on
the site and publishes them. A type with the same slug is never touched. `webx:setup` runs this for
the modules it installs.

`Seo::put($key, $block)` in `webx-ui/module-seo` keeps one JSON-LD block per key for the request.
The last one put wins, and `@webxSeo` prints it after the pushed ones. Two FAQ blocks on one page
now give one `FAQPage`, not two.
