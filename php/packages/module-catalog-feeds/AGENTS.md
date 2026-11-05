# webx-ui/module-catalog-feeds

Product feeds of the catalogue: files for marketplaces and ad platforms, made from a template. A
satellite of `webx-ui/module-catalog`; it works only through the core's registries. For products,
the filter and `ProductVariables`, read the core's `AGENTS.md`.

The package is built in stages. Today it holds the tables, the models, the feeds' CRUD API and the
section of the panel. Builds, the public address, presets and MCP tools are not here yet.

## What it owns

- **Tables** `catalog_feeds` (`WebxUi\CatalogFeeds\Models\Feed`), `catalog_feed_builds`
  (`FeedBuild`), `catalog_feed_build_rejects`, `catalog_feed_exclusions`,
  `catalog_feed_categories`, `catalog_feed_taxonomies`, `catalog_feed_taxonomy_items`.
- **Key** `access_key`: 40 random characters made when a feed is created. It is in `$hidden` and
  never in the journal. `FeedResource` gives the address that holds it only to `catalog.manage`.
- **Journal** type `catalog.feed`; `Feed::HISTORY` names the fields it follows.
- **Panel**: module `catalog-feeds` in the catalogue's group, after «Landings» (order 304). The
  API is `catalog/feeds` under the panel's API path. `FeedForm` is the one way to write a feed.
- **Currency**: `FeedForm` always stores `webx-catalog.price.currency` and refuses any other.

### Permissions

None of its own: the catalogue's permissions (`webx-ui/module-catalog`) open it, and the section
shows to whoever holds `catalog.view` or `catalog.manage` (`visibleWith()`).

| Permission       | Opens                                                    |
| ---------------- | -------------------------------------------------------- |
| `catalog.view`   | the list and a feed, without the address                 |
| `catalog.manage` | create, edit and delete a feed; the address with its key |

A route the site adds next to the module asks the same: `cms.can:catalog.view,catalog.manage` to
read, `cms.can:catalog.manage` to write.

## Change it without forking

| You want                   | Do this                                                      |
| -------------------------- | ------------------------------------------------------------ |
| Another prefix of the file | `WEBX_CATALOG_FEEDS_PATH`, or `path` in `webx-catalog-feeds` |
| Other words in the panel   | `php artisan vendor:publish --tag=webx-catalog-feeds-lang`   |

## Do not

- Do not print, log or put `access_key` in a resource: whoever has it reads the file.
- Do not add a `RouteType`: a feed is a file behind a key, not a page of the site.
- Do not add a currency conversion: a feed is in the site's currency, there are no rates.

## Check your work

- `tests/ApiTest.php` and `tests/AccessCoverageTest.php` of this package are green.
- `GET /api/cms/catalog/feeds` as an administrator with `catalog.view` shows no key.

## Read more

- `README.md` next to this file.
- Spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_FEEDS.md
