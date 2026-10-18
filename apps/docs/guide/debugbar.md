# The debug bar

`webx-ui/module-debug` puts a thin line at the bottom of the site's pages that tells you what the
server did for the page you are looking at: every SQL statement with its values, how long it took
and which file and line issued it, the statements repeated as a likely N+1, a timeline down to each
region and block, the blocks drawn and the theme they came from, the versioned cache read, what the
resolver found. An agent gets the same report as the MCP tool `debug_page`, so a person and an
agent look at the same numbers while they optimise a page.

It is a tool for the developer of a site, not monitoring: one page, opened by you, now. The
specification is `docs/architecture/WEBX_UI_DEBUGBAR.md` in the repository.

## Turning it on

```bash
composer require webx-ui/module-debug
```

That is all: the provider is discovered, there is no migration and no npm package. Sign in to the
panel, open any page of the site, and the line is at the bottom.

**Who sees it.** All three must hold, or the page is exactly what it was without the package:

- **not production** — with `APP_ENV=production` the package registers nothing at all: no
  middleware, listener, route, permission or MCP tool. No setting turns it on there, on purpose:
  the values of the queries are the visitors' personal data;
- **signed in to the panel** as an active administrator;
- **with the permission `debug.view`** — a super administrator has it; no role gets it on install.
  Give it to a developer's role in «Administrators → Roles», never to a content role: the bar shows
  what visitors typed into forms.

**Which pages.** A `GET` of a site page that answers HTML with a `</body>`. Not the panel or its
API, not JSON, files, redirects, a 404, previews or pages inside an iframe.

**Off on one copy:** `WEBX_DEBUGBAR=false` in `.env`. **Off for you only:** the «×» on the line
hides it to a small circle in the bottom left corner; the circle brings it back. The browser
remembers it (`localStorage['wx-debug']`), together with the height and the last tab.

A visitor pays nothing for the package: the first middleware looks for a cookie and a header,
finds neither, and nothing is subscribed. Tests hold this — a guest gets the same bytes and the
same number of queries as a site without the package.

## The line

```
Debug   SQL 42 · 18.4 ms   Repeated ×3   Slow 1   Response 96 ms   Memory 24 MB   ⚠ Errors 1
```

- **SQL** — how many statements the page made and their total time;
- **Repeated ×n** (amber) — how many shapes of statement ran three times or more;
- **Slow n** (amber) — statements of 50 ms and more;
- **Response** — from the start of the request to the response; **Memory** — the peak;
- **Errors** (red) — exceptions and errors in the log during the request.

Click it to open the panel above the line; drag its top edge (or use the arrow keys on it) to change
the height, `Esc` closes it. The details are fetched only now, once: the page itself carries the
summary and the id of the report, nothing more.

The line keeps to the left of the drawer of [the panel on the site](./site-bar.md) when that is
open, and covers the bottom 28 px of the page — it never changes the page's layout. It lives in a
shadow root: the theme of the site does not reach it, and it does not reach the theme. Below
640 px of width it shows a short summary, the panel takes the whole height and the rows become
cards.

## The tabs

| Tab          | What it shows                                                                                                                                                                       |
| ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **SQL**      | Every statement with its values in place, time, connection, where it was called and in which block; repeats grouped by shape; slow ones marked; transactions indented. Filter, sort |
| **Timeline** | boot, middleware, resolve, handler, render, every `region:*` and `block:*` as nested bars, statements as dots, the memory peak and what collecting itself cost                      |
| **Blocks**   | The regions and blocks drawn, as a tree: own and total time and statements, the type's version and theme layer, a failure; the Blade views and the theme that owns each file        |
| **Cache**    | The groups of the [versioned cache](./caching.md) read — from the memo, the store, or missed — with their versions; writes and forgets; other keys of the store                     |
| **Request**  | The page and its route, what the resolver found (type, id, language, alias), the theme chain, the view, the session, cookie names, the request and response headers                 |
| **Other**    | Outgoing HTTP, queued jobs, mail, the events counted, the log with its exceptions                                                                                                   |
| **History**  | Your last reports (twenty, for ten minutes): open the report of the previous page — the one before a redirect, for one — and come back                                              |

**Where it was called** is the first frame outside the framework and outside the bar itself:

- `webx-ui/module-blog · src/Feed.php:84` — a package of the platform;
- `app/Http/Controllers/HomeController.php:12` — the site's own code;
- `theme-default · views/layouts/app.blade.php` — a compiled Blade view, mapped back to its source;
- `block cards (type v4)` — the compiled template of a block type.

Next to it is the block or region being drawn at that moment, so a repeat inside a card of a list
reads as «in the block `cards`». In the Blocks tab, **Open in panel** leads to the entity the page
belongs to or to the region's screen — only when you may edit it.

## Reading repeats and slow statements

**A repeat** is one shape of statement — the SQL with its values replaced by `?`, any length of
`in (?, ?, ?)` counted as one — run more than once. The SQL tab lists the shapes above the
statements, most frequent first; from three times on (`webx-debug.repeat`) a shape is marked
**Likely N+1**.

1. Read the **where** of the group, not of a single statement: it names the template or the class
   that asks once per row. **Show these** narrows the list to that shape.
2. A shape that ran as many times as the list has rows is a relation the template touches and the
   list did not load. Load it where the list is built — `->with(['routes', 'cover'])` — as
   [Performance](./performance.md) describes.
3. A shape repeated a few times with the same values is usually a lookup with no request memo — ask
   it once and keep the answer for the request.
4. Reload the page: the count of the shape is 1, or the shape is gone. Then pin it with
   `assertNoRepeatedQueries` in the module's `PerformanceTest`, so it does not come back.

**A slow statement** (50 ms and more, `webx-debug.slow_ms`) is highlighted. Use **Copy EXPLAIN** on
it and run that on MariaDB, not on sqlite: `type: ALL` over a large table means a missing index for
its `WHERE` or `ORDER BY`. **By time** sorts the slowest first; a click on a bar of the Timeline
narrows the SQL tab to the statements made inside it — the way to see what one block costs.

Two numbers mislead if you do not know them:

- **The first visit after a save is cold.** The Cache tab shows misses, and the page makes more
  statements than it will. Look at the second load; misses on a warm page mean an entry that is not
  cached or a group that moves too often.
- **Collecting costs time.** Times are as measured, the cost of the collector is shown apart on the
  Timeline. Compare one page before and after, on the same machine, rather than reading the
  milliseconds as the production ones. The count of statements is exact: the bar never asks the
  database itself.

«Early phase not recorded» on the line means collecting started only at the end of the `web`
group: the request came without the `wx_debug` cookie, which marks you for the first middleware —
say, the first page after you were given `debug.view`. That page gets the cookie; the next one has
the whole request, global middleware and session included.

## What is kept and what is masked

- The report is written to the cache store (`WEBX_DEBUGBAR_STORE`, the site's default if empty) for
  ten minutes (`ttl`), and an administrator keeps the last twenty (`keep`). There is no table.
- It is read from `GET /api/cms/debug/reports/{id}` by the administrator who made it; anybody
  else's id answers 404, like an expired one.
- Masked while the report is written, so no unmasked copy exists: values bound to password, token
  and secret columns (`mask.columns`), any value of a statement on `sessions`, `cache`, `jobs`,
  `oauth_*` and the other tables of `mask.tables`, the headers `Authorization`, `Cookie`,
  `Set-Cookie` and the CSRF ones (and `mask.headers`), the values of cookies and of the session
  except the language and the consent, query parameters of outgoing HTTP, the context of the log,
  the local part of e-mail addresses.
- Personal data in other columns is not masked — a report is not something to paste into a public
  issue.

## For agents: `debug_page`

The MCP tool `debug_page` (scope `debug:read`, permission `debug.view`, not in production) opens a
path of the site over HTTP as a visitor's browser would, and returns the report that page left:

```json
{ "url": "/blog", "include": ["repeated", "queries"] }
```

- `url` — a path of this site with its language prefix and query; a scheme, a host or `//` is
  refused;
- `include` — the sections besides the summary: `queries`, `repeated`, `timeline`, `blocks`,
  `views`, `cache`, `request`, `other`, `log`; everything but `views` and `log` when omitted;
- `locale` — sent as `Accept-Language`.

The answer has `id`, `status`, `summary` (`queries`, `sql_ms`, `response_ms`, `memory_mb`,
`repeated`, `slow`, `errors`, `collector_ms`), `thresholds` and the sections asked for.
`repeated[]` is the shapes with `count`, `time`, `likely` and `sites` — where each one came from,
most frequent first.

The request carries a one-time token of the agent's administrator instead of a session, so the page
answers as it would a visitor and the numbers are the ones a person sees. A site that cannot reach
itself by its own name — inside Docker, behind NAT — sets `WEBX_DEBUGBAR_RESOLVE_TO` to the address
to connect to; the `Host` stays the site's.

The habit for an agent: call it **before and after** a change to a page, compare
`summary.queries`, `summary.repeated` and `summary.sql_ms`, and read `repeated[].sites` for where an
N+1 comes from.

## Settings

`php artisan vendor:publish --tag=webx-debug-config` writes `config/webx-debug.php`, every key
described in the file:

| Key                             | Default            | What                                                       |
| ------------------------------- | ------------------ | ---------------------------------------------------------- |
| `enabled`                       | `WEBX_DEBUGBAR`    | on or off outside production                               |
| `store`, `ttl`, `keep`          | default, 600, 20   | where reports live, for how many seconds, how many per you |
| `slow_ms`, `repeat`             | 50, 3              | when a statement is slow, when a shape is a likely N+1     |
| `limits`                        | 1000 statements, … | past these the report counts on and says it was truncated  |
| `mask.columns/tables/headers`   | passwords, tokens  | what never reaches a report (`*` is any run)               |
| `events`                        | a few of ours      | the event classes counted in «Other»                       |
| `mcp.resolve_to`, `mcp.timeout` | —, 30              | how `debug_page` reaches the site                          |

The words of the bar are in the package's `lang/*/bar.php`, in the ten languages of the panel
(`vendor:publish --tag=webx-debug-lang` to change them).

## For a module of your own

The bar has no API for a collector or a tab of your own: the format of the report is the bar's,
and it changes with the bar. What a module or a site does have:

- **Its statements, views, cache groups, mail, jobs, HTTP and log are already there** — collected
  from Laravel's events, attributed to `webx-ui/<package> · src/…` or to the site's file.
- **An event of its own, counted.** Add the class to `events` in the config; the Other tab shows
  how many times it was dispatched. One by one, never all events: a wildcard listener would cost on
  every event of every page.
- **A note in the log.** `Log::debug('…', [...])` appears in the Other tab with its context
  (masked by `mask.columns`).
- **Time of its own part.** Draw it as a block or in a region: each block and region is a bar on the
  Timeline with its own statements.

The platform's packages tell the bar what they do through events sent only while somebody listens —
`Routing\Events\Resolving`/`Resolved`, `Blocks\Events\RegionRendering`/`RegionRendered`/
`BlockRendering`/`BlockRendered`, `Cache\Events\GroupRead`. Without the bar their cost is one
`hasListeners()` check. They follow the bar's needs, so do not build site logic on them. A package
that needs a measure point of its own follows the same rule and adds a reader to `module-debug` —
an issue in the repository is the place to start.
