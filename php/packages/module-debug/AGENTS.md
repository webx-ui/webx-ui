# webx-ui/module-debug

A debug bar at the bottom of the site's pages, for an administrator with `debug.view`, and
never in production: every SQL statement of the page with its values, time and the file:line
that issued it, repeats grouped as likely N+1, a timeline down to each region and block, the
blocks drawn and the theme layer of their types, the versioned cache read (`webx-ui/cache`),
what the resolver found (`webx-ui/routing`). The same report goes to an agent as the MCP tool
`debug_page`. The panel and its administrators are `webx-ui/module-admin` and
`webx-ui/module-auth` — read their guides for those.

## What it owns

- **Nothing in production.** With `APP_ENV=production` the provider merges its config and
  stops: no middleware, listener, route, module, permission or tool. No setting changes that.
- **Middleware**: `Http\StartCollecting` first in the global stack (starts collecting when the
  request carries the cookie `wx_debug` or the header `X-Webx-Debug`), `Http\CollectLate` last
  in `web` (keeps the cookie true, starts late for an administrator without it).
- **Listener** on `RequestHandled` (`Http\Insertion`): checks the page and the administrator
  for real, then puts one `<script type="module" data-wx-debug="…">` before `</body>` — the
  report id and the summary only — and `Cache-Control: private, no-store`.
- **Reports**: in a cache store (`webx-debug.store`), key `webx:debug:<ulid>`, TTL
  `webx-debug.ttl` (600 s), the last `webx-debug.keep` (20) per administrator. No table.
- **Routes**: `GET /cms/_debug/bar.{hash}.js` (the script), `GET /api/cms/debug/reports` and
  `GET /api/cms/debug/reports/{id}` behind `cms.can:debug.view`, only the maker's — anybody
  else's id is a 404.
- **Permission** `debug.view` (module `debug`), given to no role on install.
- **MCP** tool `debug_page` (scope `debug:read`, permission `debug.view`), read only.
- **Points of measure in other packages**, sent only while somebody listens:
  `WebxUi\Routing\Events\{Resolving,Resolved}`, `WebxUi\Blocks\Events\{RegionRendering,
RegionRendered,BlockRendering,BlockRendered}`, `WebxUi\Cache\Events\GroupRead`.
- **Config** `config/webx-debug.php`: `enabled` (`WEBX_DEBUGBAR`), `store`, `ttl`, `keep`,
  `slow_ms` (50), `repeat` (3), `limits`, `mask.{columns,tables,headers}`, `events`,
  `mcp.resolve_to` (`WEBX_DEBUGBAR_RESOLVE_TO`), `mcp.timeout`.

## Change it without forking

| You want                                        | Do this                                                            |
| ----------------------------------------------- | ------------------------------------------------------------------ |
| The bar off on this copy                        | `WEBX_DEBUGBAR=false` in `.env`                                    |
| A developer who is not a super administrator    | give their role `debug.view` in «Administrators → Roles»           |
| Another column or table never shown in values   | add it to `mask.columns` / `mask.tables` (`*` is any run)          |
| A header never shown                            | add its name to `mask.headers`                                     |
| A statement marked slow sooner or later         | `slow_ms` in the config                                            |
| An event of your own counted in «Other»         | add its class to `events`                                          |
| Reports kept elsewhere than the default cache   | `WEBX_DEBUGBAR_STORE=<store>`                                      |
| `debug_page` on a site that cannot reach itself | `WEBX_DEBUGBAR_RESOLVE_TO=<ip>` — the Host stays the site's        |
| The config or the words in the site             | `vendor:publish --tag=webx-debug-config` / `--tag=webx-debug-lang` |

## Do not

- Do not edit anything in `vendor/webx-ui/module-debug` or copy it into the site. The rows
  above are the supported ways; if none fits, the package is missing a seam — say so.
- Do not try to turn the bar on in production — there is no switch for that, on purpose:
  values of queries are visitors' personal data.
- Do not grant `debug.view` to a content role. It shows what visitors typed into forms and what
  the site stores about them.
- Do not paste a report into a public issue: secrets are masked, personal data is not.
- Do not listen to `BlockRendering` and the other points of measure for site logic: they are
  sent only while somebody listens, and their shape follows the bar's needs.
- Do not put the report into the page or read it from the page's markup: the tag carries the
  id; the details are the API's, for the same administrator.

## Check your work

- Open a page signed in as a super administrator: the line at the bottom shows the number of
  statements, their time, repeats and the response time. Signed out, or on production, the
  page source has no `data-wx-debug`.
- With MCP: `debug_page {"url": "/"}` before and after an optimisation; compare
  `summary.queries`, `summary.repeated`, `summary.sql_ms`, and read `repeated[].sites` for where
  an N+1 comes from.
- `GET /api/cms/debug/reports` lists your latest reports; an id that is not yours is a 404.

## Read more

- [README.md](README.md) in this directory — how a request is collected and decided.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_DEBUGBAR.md
- Performance rules the numbers are for: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_PERFORMANCE.md
- The versioned cache: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CACHE.md
