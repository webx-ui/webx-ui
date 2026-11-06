# webx-ui/module-catalog-feeds

Product feeds of the catalogue: files for marketplaces and ad platforms, made from a template. A
satellite of `webx-ui/module-catalog`; it works only through the core's registries. For products,
the filter and `ProductVariables`, read the core's `AGENTS.md`.

## What it owns

- **Tables** `catalog_feeds` (`WebxUi\CatalogFeeds\Models\Feed`), `catalog_feed_builds`
  (`FeedBuild`), `catalog_feed_build_rejects`, `catalog_feed_exclusions`,
  `catalog_feed_categories`, `catalog_feed_taxonomies` (`Taxonomy`),
  `catalog_feed_taxonomy_items` (`TaxonomyItem`).
- **Key** `access_key`: 40 random characters made when a feed is created. It is in `$hidden` and
  never in the journal. `FeedResource` gives the address that holds it only to `catalog.manage`.
- **Journal** type `catalog.feed`; `Feed::HISTORY` names the fields it follows.
- **Panel**: module `catalog-feeds`, after «Landings» (order 304); the form is `catalog.feed-form`,
  its node ids are public (`apps/docs/guide/catalog-feeds.md`). `FeedForm` alone writes a feed.
- **MCP** `catalog_feeds_*` (`Mcp\FeedTools`), `catalog://feed-variables` and `catalog://feed-rules`
  (`Mcp\FeedResources`), through `SatelliteTools`: scopes `catalog:read`, `catalog:write`.
- **Currency**: `FeedForm` always stores `webx-catalog.price.currency` and refuses any other. A
  build of a feed that holds another one fails until the feed is saved again.

### Permissions

None of its own: the catalogue's permissions (`webx-ui/module-catalog`) open it, and the section
shows to whoever holds `catalog.view` or `catalog.manage` (`visibleWith()`).

| Permission       | Opens                                                           |
| ---------------- | --------------------------------------------------------------- |
| `catalog.view`   | the list, a feed without the address, the count, the journal    |
| `catalog.manage` | write, preview, rebuild, exclude, map; the address with its key |

A route the site adds next to the module asks the same: `cms.can:catalog.view,catalog.manage` to
read, `cms.can:catalog.manage` to write.

### How a feed is checked

- `FeedTrial` is §8.1: syntax, names (`ProductVariables` for the item, `FeedFrame::variables()` for
  the head and the foot), CDATA, currency, a sample of `sample` products, the parser of the format.
  The first failure is the answer. `check()` serves `FeedForm::save()`, `preview()` the route.
- `FeedForm::save()` tries a feed only when `TRIED` fields changed: a feed gone bad with the catalogue
  can still be renamed or switched off. `fill()` puts the values on a feed without writing it.
- `FeedRender` is the code a build and a trial share, so a sample that passes means the file will.
  Render through it; what is rendered elsewhere proves nothing about the file.
- The sample looks at no more than 1000 products for its `sample`, so `rejected` in a preview speaks
  about those looked at; `selected` is the whole of what the filter passes.
- A preview's mistake is a 200 with `ok: false`, a save's the 422 under the field.
- JSON is read twice: each product by itself and then the whole, as a build does. `jsonText()`
  gives the line, and it is for samples: `seld/jsonlint` wants the whole text in memory.
- Templates reach the API untrimmed (`keepTemplatesAsTyped()`): the line break after a csv's head
  is what keeps the first product off the line of the column names. The skip takes the whole
  request, so every other field of the form goes through `FeedForm::cleaned()`.

### How a build goes

- `FeedBuilder::start($feed, $trigger)` queues `BuildFeed`, one build a feed at a time. Nothing else
  starts a build. A build silent for `stale_minutes` is closed by the next start.
- `FeedSelection` is the filter and the exclusions, `FeedChecks` the third layer and the warnings.
  A filter naming a facet the site has not fails the build: it is never dropped in silence.
- Pages go by the cursor `id` into `storage/app/catalog-feeds/<build>.body`. The cursor, the counters,
  `state`, the rejects and `body_bytes` are written together, and a job starts by cutting the body to
  `body_bytes`: that is what makes a repeated page harmless.
- The head and the foot render last (`FeedFrame`), `FeedFileCheck` reads the joined file, and only
  then does `current_build_id` move. A failed build leaves the file served as it was.
- A `FeedFormat` in `FeedFormats` (§10) replaces the template in the same steps and is offered as a
  preset: `driver` is set from that preset alone. No state in the class: `FeedWriter::written()`.
- After the pages each step is a job of its own, the step kept in `state.step`: `join`, `check` (a csv
  in stretches of `job_seconds`, `state.checked` says where it stopped), `publish`. A repeated job
  repeats its step.
- `BuildFeed::$timeout` is 80: above the 60 of `job_seconds` by the page in hand, under the 90 of the
  skeleton's `retry_after`. A job without its own timeout dies at the worker's 60 with its budget used
  and never queues the next one. `budget()` keeps a raised `job_seconds` under it.
- `FeedVariables` holds what a feed overrides in `ProductVariables`: `availability`, `mpn`,
  `platform_category` with `google_product_category`, and through `FeedLabels` `custom_label_0…4`.

### How the mappings work

- `FeedCategories` reads `catalog_feed_categories` into a `CategoryMap`: a category's row, or the
  nearest ancestor's. The file gets the list's `external_id`, or the value typed by hand.
- A list is stored under the language of its file, `en-US`, not `en`. `Taxonomies::of()` gives a
  language its own list or the English one, and `fallback` says which. The ids are the same.
- `TaxonomyImport` is the one way in: `queue()` by address (`ImportTaxonomy`, `RemoteFetcher`,
  https only), `upload()` by a file under the purpose `catalog.feed-taxonomy`, read at once. Google
  answering 404 for a language moves the import to `en-US`; an address a person gave does not.
- A new list updates rows in place, so mappings hold. A category it dropped leaves its id in `value`.
- A source of a label is looked up when the label is printed: an unused one cannot fail a build.
  `to` of a price range belongs to it. Only `FeedLabels` knows the sources; `FeedForm` asks it.
- A property of several values gives a label its first through the core's `FirstValues`.

### How a preset works

- `FeedPresets` holds the `FeedPreset` classes: `google`, `json`, `xml`, `csv`. `GET meta` offers them.
- `FeedForm::fromPreset()` copies a preset under what the form sent, for a new feed only. After that
  `preset` is a word on the feed: nothing reads it, and the preset may change or go.

### How a file gets out

- `GET /{path}/{key}.{ext}` is `FeedFileController`, outside every group, behind the limiter
  `webx-catalog-feeds` (by key and address). A request never builds and reads one row of the feed.
- A wrong key, an old key, a feed switched off and a feed without a file are the same empty 404;
  `Retry-After` goes only to the right key. Another extension of a right key answers 301.
- A feed whose format changed has no file until it is built: the old one is not served as the new.
- `FeedKey::reissue()` is the only way to change a key: one transaction, one `key_reissued` row.
- `FeedWatch` (`webx:catalog:feeds`, every minute) decides and queues. `dirty_since` moves with each
  new generation, for `quiet_minutes`; `first_dirty_at` stays, for `max_wait`. A running import
  holds a build; `min_interval` holds in any case. The last build counts even if it failed.
- `FeedPrune` (`webx:catalog:feeds-prune`, daily) keeps the file served and `keep_files` before it.
- `FeedsCheck` in `webx:doctor` names feeds, never keys. Only `Doctor\RobotsRules` reads robots.txt.

## Change it without forking

| You want                      | Do this                                                      |
| ----------------------------- | ------------------------------------------------------------ |
| Another prefix of the file    | `WEBX_CATALOG_FEEDS_PATH`, or `path` in `webx-catalog-feeds` |
| Other words in the panel      | `php artisan vendor:publish --tag=webx-catalog-feeds-lang`   |
| Another disk for the files    | `WEBX_CATALOG_FEEDS_DISK`; a public disk is refused          |
| Another size of a page        | `chunk` in `webx-catalog-feeds`                              |
| Other limits of the watch     | `quiet_minutes`, `min_interval`, `max_wait`                  |
| A bigger or smaller sample    | `sample` in `webx-catalog-feeds`                             |
| Another limit of label values | `max_label_values` in `webx-catalog-feeds`                   |
| A larger file of a list       | `taxonomy_max_bytes` in `webx-catalog-feeds`                 |
| Open feeds on a closed site   | `feeds/*` in `webx-admin.gate.except`                        |
| A preset of your own          | `FeedPresets::register()` after it resolves                  |

## Do not

- Do not print, log or put `access_key` in a resource: whoever has it reads the file.
- Do not rehearse `delete`, `rebuild`, `reissue_key` or `taxonomy_import`: a rollback brings back no file, no job.
- Do not show the address to a read-only connection or a `catalog:read` key: `FeedTools::seesAddress()`.
- Do not add a `RouteType`: a feed is a file behind a key, not a page of the site.
- Do not add a currency conversion: a feed is in the site's currency, there are no rates.
- Do not add a fourth reason to `FeedChecks::reason()`: what does not take a product out is a warning.
- Do not write the watcher's columns through the model: a note is no edit, and it would touch
  `updated_at`.
- Do not start a build from a request to the address, and do not answer 403 there: it confirms a key.
- Do not read a variable per product: a page is asked at once, or a build turns into an N+1.
- Do not delete and insert a list on import: `taxonomy_item_id` points at its rows.
- Do not split `property.*` by `;` for one value: a name can hold one, ask `FirstValues`.
- Do not print an attribute of Google outside a section if it can be empty: Google wants it taken out.

## Check your work

- Everything in `tests/` is green. `tests/fixtures/preview.json` is what the browser's preview is
  held to: rewrite it with `WEBX_WRITE_FIXTURES=1` and read the diff.
- `GET /api/cms/catalog/feeds` as an administrator with `catalog.view` shows no key.

## Read more

- `README.md` next to this file.
- Spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_FEEDS.md
