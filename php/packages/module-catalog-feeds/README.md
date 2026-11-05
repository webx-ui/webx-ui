# webx-ui/module-catalog-feeds

Product feeds for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel:
files for marketplaces and ad platforms, set up in the panel from a template. A satellite of
`webx-ui/module-catalog`.

Specification: `docs/architecture/WEBX_UI_CATALOG_FEEDS.md` in the monorepo.

The package is being built in stages. What works today: the tables, the section of the panel and
the feeds' API. Building a file, serving it, presets and the agent's tools come next.

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

## Builds

`FeedBuilder::start($feed)` queues a build, so a site needs a live queue. It walks the products a
page at a time, keeps the file on `WEBX_CATALOG_FEEDS_DISK` (private, `local` by default) and checks
it before the feed moves to it: a build that fails leaves the previous file in place. On MariaDB,
100 000 products with a full template took about 42 seconds and 73 MB in one worker.

## API

Under the panel's API path: `GET`, `POST catalog/feeds`; `GET`, `PUT`, `DELETE catalog/feeds/{id}`.

## License

MIT
