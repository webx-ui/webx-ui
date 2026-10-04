# webx-ui/module-catalog-manticore

Manticore Search (version 29 or later) as the catalogue's engine. It answers lists, filters with
their counts, and search for catalogues past the couple of thousand products the SQL engine
handles. It keeps one table per language with that language's morphology. It finds codes by any
part of them and corrects a wrong keyboard layout. A rebuild fills new tables and swaps them in
whole. The database stays the place everything is written to, and a product page is always read
from the database. This package is a satellite of `webx-ui/module-catalog`, which owns the
indexing queue, `webx:catalog:index` and the engine registry; read the core's `AGENTS.md` for
those.

## What it owns

- **Engine** `manticore` in the core's `CatalogEngines`, chosen with
  `WEBX_CATALOG_ENGINE=manticore`.
- **Tables on the Manticore server** named `{prefix}_catalog_products_{locale}`, plus
  `{table}_next` during a rebuild. The package touches nothing else on the server. It has no
  database migrations of its own.
- **Panel**: module `search-index` («System → Search index»), shown only on this engine. It shows
  the server, each language's table compared with the database, the queue, and «Rebuild». The API
  is under `/api/cms/search-index` (`rebuild`). Permissions are `search-index.view` and
  `search-index.manage`.
- **View** `webx-catalog-manticore::unavailable`: the 503 page with `Retry-After`, shown when the
  server is down and the catalogue is larger than `webx-catalog.sql_engine_limit`.
- **Doctor check**: it reports a missing prefix, a prefix that starts somebody else's tables, a
  server that does not answer, and a table that is out of date.
- **MCP** tool `catalog_index_status` (read only). It reports the server, the tables and the
  queue, and with `product` it explains why one product is not found.

## Change it without forking

| You want                             | Do this                                                                                     |
| ------------------------------------ | ------------------------------------------------------------------------------------------- |
| Connect the server                   | `MANTICORE_HOST`, `MANTICORE_PORT`, and the required `MANTICORE_TABLE_PREFIX` (`[a-z0-9_]`) |
| Rebuild jobs on a queue of their own | `MANTICORE_REBUILD_QUEUE`                                                                   |
| Morphology for another language      | `morphology` in `config/webx-catalog-manticore.php` (`--tag=webx-catalog-manticore-config`) |
| Another keyboard layout corrected    | `layouts` in the same file                                                                  |
| Shorter word beginnings / parts      | `min_prefix_len`, `min_infix_len`; ranking of own vs other languages: `weights`             |
| Timeouts and how long «down» is kept | `connect_timeout`, `timeout`, `down_for`                                                    |
| A different 503 page                 | `php artisan vendor:publish --tag=webx-catalog-manticore-views`                             |
| Other words in the panel             | `php artisan vendor:publish --tag=webx-catalog-manticore-lang`                              |

The «Showing results for …» line and its view belong to the core: `webx-catalog::search-corrected`.

## Do not

- Do not leave `MANTICORE_TABLE_PREFIX` empty, and do not copy one site's prefix to another site
  on the same server. Two sites would write into each other's tables. Give each copy of a site
  its own prefix.
- Do not switch `WEBX_CATALOG_ENGINE` to `manticore` on a catalogue with products and leave it
  there. The tables start empty. Run `php artisan webx:catalog:index --rebuild` once.
- Do not create, alter or drop the `{prefix}_catalog_products_*` tables by hand. A schema change
  (a new language, a morphology, a module update) needs a rebuild. The rebuild fills `_next`
  tables and swaps them in while the storefront keeps reading the old ones.
- Do not start a rebuild from an agent. There is no tool for it on purpose. A rebuild is minutes
  of load, so a person chooses when, from the panel or the console. It also needs a queue worker.
- Do not make saving wait for Manticore, or write to it from your own code. Saving marks the
  product in the core's queue, and `webx:catalog:index` on the schedule writes it. A satellite's
  data reaches the index through the core's `Documents` registry.

## Check your work

- `php artisan webx:doctor`: prefix, server, and tables out of date.
- `php artisan webx:catalog:index` runs on the schedule every minute. A product just saved is
  found by search after the next run.
- «System → Search index» in the panel: the product count per table equals the database's count,
  and the queue empties.
- With MCP: `catalog_index_status`, with `product` for a product that is not found.

## Read more

- [README.md](README.md) in this directory: tables per language, search, rebuilding, outages.
- Guide: https://webx-ui.github.io/webx-ui/guide/catalog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_MANTICORE.md
- The core: `webx-ui/module-catalog` and its `AGENTS.md`.
