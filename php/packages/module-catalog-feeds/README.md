# webx-ui/module-catalog-feeds

Product feeds for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel:
files for marketplaces and ad platforms, set up in the panel from a template. A satellite of
`webx-ui/module-catalog`.

Specification: `docs/architecture/WEBX_UI_CATALOG_FEEDS.md` in the monorepo.

The package is being built in stages. What works today: the tables, the panel with the form of a
feed, its API, building a file, serving it, the presets and the agent's tools (`catalog_feeds_*`).

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-catalog` and what it requires

## Install

```bash
composer require webx-ui/module-catalog-feeds
php artisan migrate
```

Set the site's currency with `WEBX_CATALOG_CURRENCY`: a feed is always in it.

There are no permissions of its own: `catalog.view` reads the feeds, `catalog.manage` writes them
and alone sees the address with its key.

## Presets

`POST catalog/feeds` with `preset` set to `google`, `json`, `xml` or `csv` copies a ready template
into a new feed; whatever else the request sends wins over it. `GET catalog/feeds/meta` lists them.
The copy is the feed's own: a later release of a preset does not touch it. The Google preset sends
only the main `gtin`; to send every barcode, change that line of the copy to
`{{#gtins}}<g:gtin>{{value}}</g:gtin>{{/gtins}}`.

## Builds

`FeedBuilder::start($feed)` queues a build, so a site needs a live queue. It walks the products a
page at a time, keeps the file on `WEBX_CATALOG_FEEDS_DISK` (private, `local` by default) and checks
it before the feed moves to it: a build that fails leaves the previous file in place. On MariaDB,
100 000 products through a real `queue:work` took about 22 seconds and 78 MB, in four jobs.

A job keeps its own timeout of 80 seconds, so a worker needs no `--timeout`. Keep the queue's
`retry_after` above it (90 in the skeleton), or a second worker takes a job that is still alive.

## The address

A feed answers at `/feeds/{key}.{ext}`, with `ETag`, `Last-Modified` and 304; the prefix is
`WEBX_CATALOG_FEEDS_PATH`. The address does not change when the feed is edited. If it leaks,
`POST catalog/feeds/{id}/reissue-key` replaces the key: the old address answers 404 at once, the new
one works without a build, and the marketplace has to be given it by hand.

A site behind its password closes the feeds too. Open them with `feeds/*` in
`webx-admin.gate.except`; `webx:doctor` says so, and says when robots.txt closes the feeds or the
images to Googlebot.

## The schedule

The site's scheduler has to run: `webx:catalog:feeds` every minute starts what is due,
`webx:catalog:feeds-prune` daily removes old files and the old journal. After a change of the
catalogue a feed with `rebuild_on_change` waits for 10 quiet minutes and for a running import, comes
no oftener than once an hour and no later than 6 hours after the first change.

## The check

A feed that would not make a valid file is not kept. On saving, the templates are read, the names
they use are checked, `sample` real products go through the build's own code and the result through
the parser of the format; a refusal is a 422 under the field, and a mistake in the sample names its
line, the part of the template and the text around it. A warning that stopped nothing comes in
`warnings` next to `data`. Changing only the name, the schedule or `is_active` tries nothing.

`POST catalog/feeds/preview` takes the values of the form and answers with the same check, keeping
nothing: the sample's `text`, `errors`, `warnings`, how many products the filter passes and what
became of those looked at. `feed` names the saved feed they belong to; `product_ids`, up to 20, look
at those products and say why each one that stays out does.

A feed is in the site's currency. After the site changes it, a build fails until the feed is saved
again, so somebody looks at a template that may print the old one.

## Mappings

`stock_map` turns a status of the stock satellite into a marketplace's word; a status without a row
keeps `in_stock` or `out_of_stock`. `preorder` saves with a warning: Google asks for a date the
catalogue does not have.

A category takes the value of its nearest mapped ancestor, so a branch is mapped by its top. A
category with no value leaves the tag out, stays in the feed and counts as `unmapped_category` in
the journal.

Google's list comes in by address, through the queue, or as an uploaded file:

```
POST /api/cms/catalog/feeds/taxonomies/google/import   { "locale": "de" }            202
POST /api/cms/catalog/feeds/taxonomies/google/import   { "locale": "de", "upload": "<id>" }   200
GET  /api/cms/catalog/feeds/taxonomies/google/search?locale=de&q=shoes
```

Google has no file for some languages. Such a language reads the English list, and `fallback` in the
answer says so: the ids are the same, only the words in the panel are English.

`custom_labels` gives each of `custom_label_0…4` one source:

```json
{
  "0": { "type": "label", "labels": ["sale", "new"] },
  "1": { "type": "price_range", "ranges": [{ "to": 50, "value": "low" }, { "value": "high" }] },
  "2": { "type": "property", "property": "material" },
  "3": { "type": "constant", "value": "autumn-campaign" },
  "4": { "type": "none" }
}
```

A value is cut to 100 characters. `to` belongs to its range: a price of 50 is `low` here. More than
`max_label_values` different values of a label is a `label_values` warning in the journal.

`sources.mpn` is `{ "type": "field" }`, or `{ "type": "property", "property": "<code>" }`.

## Demo

`php artisan webx:demo` adds one Google Merchant feed to the demo shop, daily and rebuilt on change.
It is saved without a build, because the demo of the stock runs after it: the next minute of the
scheduler queues the first one, or press «Build» in the panel. The site needs a currency
(`WEBX_CATALOG_CURRENCY`), and a site with a feed of its own is left alone.

## API

Under the panel's API path: `GET`, `POST catalog/feeds`; `GET`, `PUT`, `DELETE catalog/feeds/{id}`;
`GET catalog/feeds/meta`, `variables`, `facets`; `POST catalog/feeds/count`, `sample`, `preview`;
`POST catalog/feeds/{id}/rebuild`, `reissue-key`; `GET catalog/feeds/{id}/builds`, `builds/{build}`;
`catalog/feeds/{id}/exclusions`, `categories`; `catalog/feeds/taxonomies/{key}/search`, `import`.

## License

MIT
