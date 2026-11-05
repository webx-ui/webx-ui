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

## API

Under the panel's API path: `GET`, `POST catalog/feeds`; `GET`, `PUT`, `DELETE catalog/feeds/{id}`.

## License

MIT
