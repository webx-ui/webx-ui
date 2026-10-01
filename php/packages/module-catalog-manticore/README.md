# webx-ui/module-catalog-manticore

[Manticore Search](https://manticoresearch.com) as the engine of the catalogue of the
[WebX UI](https://github.com/webx-ui/webx-ui) admin panel — for catalogues the database engine no
longer carries (more than a couple of thousand products). Category pages, filters with their
counts, the search and the panel's product list are answered by Manticore; the product page itself
never is.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-catalog` and what it requires
- A Manticore Search server (29 or later) reachable over its HTTP JSON API. The package does not
  install or configure it, and one server may serve several projects.

## Install

```bash
composer require webx-ui/module-catalog-manticore
```

```dotenv
WEBX_CATALOG_ENGINE=manticore
MANTICORE_HOST=127.0.0.1
MANTICORE_PORT=9308
MANTICORE_TABLE_PREFIX=my_shop
```

```bash
php artisan webx:catalog:index --rebuild
```

The prefix is required and has no default: two copies of one site on one server would otherwise
write into each other's tables. It is `[a-z0-9_]`; the tables are
`{prefix}_catalog_products_{locale}`, and the package touches nothing else on the server.
`php artisan webx:doctor` says when the prefix is missing, when it begins somebody else's tables,
when the server does not answer and when a table is out of date.

From there the core's queue keeps the index current: saving a product marks it, and
`webx:catalog:index` on the schedule writes the marked ones every minute.

## A table per language

Every language of the site has a table, and every table holds every language: its own in the main
text columns, weighed higher, and the others in `other_languages`. A product is found by a word in
any language of the site from a page in any language, and its own language ranks first. A product
without a translation is indexed with the text of the main language, as the storefront shows it.

The morphology of a table is that of every language of the site, its own first
(`webx-catalog-manticore.morphology`); a language not named there is indexed without morphology.
The beginning of a word is always searched.

## Rebuilding

`webx:catalog:index --rebuild` fills `{table}_next` beside each live table and swaps it in when it
is whole; the storefront reads the old tables meanwhile. A rebuild is needed when Manticore is
connected to a catalogue that already has products, when the schema changes (a language added, a
morphology changed, a new version of a module that writes into the index), or when the server lost
its data. `webx:doctor` reports a table that is out of date; when to rebuild is yours to choose.

## When the server does not answer

The failure is remembered for `down_for` seconds (30), so nobody waits for it twice. Meanwhile the
panel reads the database, and so does a storefront whose catalogue is within
`webx-catalog.sql_engine_limit`; a larger catalogue answers 503 with `Retry-After`. Saving a product
never fails because of Manticore: the product waits in the queue.

## Config

```bash
php artisan vendor:publish --tag=webx-catalog-manticore-config
```

`connect_timeout`, `timeout`, `down_for`, `morphology`, `min_prefix_len`, `weights`,
`facet_values`, `relevance_sample` — see the comments in the file.

## License

MIT
