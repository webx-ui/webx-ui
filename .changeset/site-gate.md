---
'@webx-ui/php': minor
---

A password over a site while it is being tested. `WEBX_SITE_GATE=true` and
`WEBX_SITE_GATE_USERS="client:secret"` put HTTP Basic in front of every address the site answers,
including addresses that do not exist. Global middleware in `webx-ui/module-admin` does this; a
member of the `web` group would let every 404 through. The panel and its JSON stay open. So do
`/.well-known`, the MCP server and its OAuth endpoints (`webx-ui/mcp`), and a block preview under
a valid token, the block editor's stage and the block bundles (`webx-ui/module-blocks`). A site
opens more with `webx-admin.gate.except`, and a package opens its own through `Gate\Openings`.
Switched on with no pairs, the gate lets nobody in, and `webx:doctor` fails on that. It also
fails when `WEBX_SITE_GATE` is set under a published config that has no `gate` block and so
closes nothing.
