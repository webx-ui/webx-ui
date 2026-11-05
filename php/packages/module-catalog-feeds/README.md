# webx-ui/module-catalog-feeds

Product feeds for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel:
files for marketplaces and ad platforms, set up in the panel from a template. A satellite of
`webx-ui/module-catalog`.

Specification: `docs/architecture/WEBX_UI_CATALOG_FEEDS.md` in the monorepo.

The package is being built in stages. What works today: the tables, the section of the panel, the
feeds' API, building a file and serving it. Presets and the agent's tools come next.

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

## API

Under the panel's API path: `GET`, `POST catalog/feeds`; `GET`, `PUT`, `DELETE catalog/feeds/{id}`;
`POST catalog/feeds/{id}/reissue-key`.

## License

MIT
