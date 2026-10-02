# webx-ui/module-audit

A site audit as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: the
checks an SEO specialist, a front-end developer and an admin run by hand on every new project,
done by the site itself, without external services.

What it checks today:

- **The production config** — `APP_DEBUG`, `APP_ENV`, `APP_URL`, a `sync` queue, a mailer that
  writes to the log, a scheduler that never runs, a missing `public/storage`, the site password.
- **The host** — both mirrors answering, http not leading to https in one 301, the certificate,
  HSTS, index files, double slashes, the trailing slash, case, soft 404s, the 404 page,
  compression, security headers, version leaks, static files without long caching.
- **Links to a development stand in the content** — every absolute address in the database,
  drafts and hidden records included, classified as the site's own, its other mirror, a stand or
  somebody else's. Content modules hand their fields over with `AuditContentSource`
  (`webx-ui/module-pages` and `webx-ui/module-blocks` do).

- **Every page** — a full run crawls the site from the home page, the sitemap and the address
  registry (`webx-ui/routing`), two requests at a time and a thousand pages at most, and keeps a
  snapshot of each: the answer, the head, the headings, the text, every link and resource. The
  page checks read it — title, description, H1, canonical, viewport, Open Graph, thin and
  duplicate content, address format, speed, broken and empty links, mixed content, pictures
  without `alt`, accessibility, depth, orphans — and so do the outgoing hosts: a stand linked
  from a page, another mirror, a host that looks like the site's own, a new outside domain.

The sitemap and robots.txt checks, redirects, hreflang, structured data and external links
follow.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-auth`, `webx-ui/module-settings`

## Install

```bash
composer require webx-ui/module-audit
php artisan migrate
```

The section appears once the front end lists it too: `audit()` from `@webx-ui/module-audit` in
`createAdmin({ modules: [...] })`. Permissions: `audit.view`, `audit.run`, `audit.manage`.

A run goes to the queue; on a `sync` queue the panel does not start one and points to the
command. The settings — the address to audit, where to connect, and the site's other addresses
(stands, staging, old domains) — are on the «Audit» tab of the site's settings.

## On deploy

```bash
php artisan webx:audit:run --quick --fail-on=error
```

The config, the host and the database in seconds, exiting with 1 when there is an error: a site
with links to a stand or with `APP_DEBUG=true` does not go out silently.

## A check of your own

```php
use WebxUi\Audit\Checks\AuditChecks;

$this->app->make(AuditChecks::class)->register(new MyCheck);
```

`MyCheck` implements `WebxUi\Audit\Contracts\AuditCheck` — `id()`, `group()`, `severity()`,
`needs()` and `run(AuditContext)`, yielding `Finding`s. Register it from your provider behind
`class_exists(AuditChecks::class)`, so your package does not need the audit installed.

The full design is in
[`WEBX_UI_MODULE_AUDIT.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_AUDIT.md).

## License

MIT
