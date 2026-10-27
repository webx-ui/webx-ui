# webx-ui/module-debug

A debug bar at the bottom of a WebX UI site's pages: what the server did for the page you are
looking at — SQL with values, repeats, a timeline with regions and blocks, the versioned cache,
what the resolver found — for an administrator with `debug.view`, and never in production. The
same report is the MCP tool `debug_page` — which a production stand may open on purpose, with
every value masked (`WEBX_DEBUG_MCP_IN_PRODUCTION`).

```bash
composer require webx-ui/module-debug
```

Nothing else: the provider is discovered, there is no migration and no npm package. A super
administrator sees the bar at once; anybody else needs a role with `debug.view`.

## How a request is decided

1. **Production** — nothing of the package is registered. Not a middleware, not a route. With
   `WEBX_DEBUG_MCP_IN_PRODUCTION=true`, only the agent's path: the module with `debug_page`, the
   first middleware for its token alone (checked before collecting) and the listener that keeps
   its report, every value masked. Never the cookie, the late start, the bar or the API.
2. **The first global middleware** starts collecting when the request carries the cookie
   `wx_debug` (set at sign-in to the panel for an administrator with the permission) or the
   header of `debug_page`'s one-time token. It reads neither: a visitor costs a look at two
   arrays, and the listeners that collect are subscribed only now.
3. **The end of `web`** — the session is known: an administrator with the permission and no
   cookie gets one, and collecting starts here if it did not start early («Early phase not
   recorded»). One who lost the permission loses the cookie.
4. **`RequestHandled`** — collecting stops, then everything is checked for real: a `GET` page of
   the site (not the panel, its API, a preview, JSON, a file, a redirect), an active
   administrator with `debug.view`. Anything false, and the response leaves untouched and what
   was collected is thrown away. Otherwise the page gets one script tag with the report id and
   its summary, and the report is written to the cache store after the response has gone.

The details are read from `GET /api/cms/debug/reports/{id}` by the same administrator, for ten
minutes. Passwords, tokens, sessions and cookies are masked while the report is written, so no
unmasked report exists anywhere.

## The report

`version`, `summary` (`queries`, `sql_ms`, `response_ms`, `memory_mb`, `repeated`, `slow`,
`errors`, `collector_ms`), `queries` (SQL, bindings, `inline`, time, connection, `site` —
`webx-ui/module-blog · src/Feed.php:84`, `resources/views/page.blade.php`, `block hero (type
v3)` —, `in` — the block or region being drawn), `repeated` (by shape, `likely` past
`webx-debug.repeat`), `timeline`, `blocks` (a tree with own and total time and statements, the
theme layer of each type), `views`, `cache` (groups with memo / store / miss and versions,
other keys), `request` (route, resolution, theme chain, headers, cookies, session — masked),
`other` (outgoing HTTP, queued jobs, mail, counted events, log).

## Configuration

`php artisan vendor:publish --tag=webx-debug-config` — every key is described in the file.

## License

MIT
