---
'@webx-ui/php': minor
---

The block constructor: the agent's doors, and the files

`webx-ui/mcp` now serves what the modules declare. `WebxServer` is a `laravel/mcp` 1.0 server
that reads the tool registry when it starts, so a panel exposes exactly the tools of the modules
it has — over Streamable HTTP at `{api_path}/mcp`, closed by `webx.mcp-auth` until a Sanctum
token opens it, and over stdio as `mcp:start webx`. `php artisan webx:mcp:token` issues a token
to an administrator with the scopes as its abilities; a scope is checked once, before any handler
runs. A handler now also receives the administrator the call acts as, and refuses with a thrown
`ToolFailure` that the agent reads verbatim.

`webx-ui/module-blocks` speaks it: `blocks_list`, `blocks_get`, `blocks_create`, `blocks_update`,
`blocks_publish`, `blocks_render`, `blocks_get_content`, `blocks_set_content` and
`blocks_preview_url`, through the same doors the panel uses and with `mcp` as the source in the
history; the resources `blocks://guidelines`, `blocks://catalog`, `blocks://fields` and
`blocks://site`; the prompt `design_block`. And the files: `webx:blocks:export` writes each type
to `resources/blocks/{slug}.json`, `webx:blocks:import` reads them back — a version only where the
content differs, `--publish` to publish what passes the checks, `--dry-run` to be told.
