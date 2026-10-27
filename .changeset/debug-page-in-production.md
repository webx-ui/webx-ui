---
'@webx-ui/php': minor
---

`module-debug`: `debug_page` can be opened on a site running as production, on purpose and alone.

- `WEBX_DEBUG_MCP_IN_PRODUCTION=true` (`webx-debug.mcp.production`, off by default) registers in
  production only the agent's path: the permission `debug.view`, the tool `debug_page` (scope
  `debug:read`), the first middleware — started by the tool's one-time token alone — and the
  listener that keeps its report. The bar, its script, the cookie `wx_debug`, the late start and
  the API of reports stay off there whatever any setting says; a visitor, a forged `X-Webx-Debug`
  and an administrator with the cookie get the page exactly as without the package.
- A report made in production keeps no value: every bound value, the session, a log line's
  context and message, an exception's message, an outgoing query and a mail's subject are masked.
  Shapes, counts, times, places and repeats stay.
- `webx:doctor` says so in a note while it is on.
- Everywhere: a token header that is not signed by the site or has expired no longer starts the
  collectors at all.
