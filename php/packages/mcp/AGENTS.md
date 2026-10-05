# webx-ui/mcp

The panel's one MCP server: it declares nothing itself and serves whatever tools, resources and
prompts the installed modules offer, over Streamable HTTP and over stdio. It also holds the
connection terms (grants), the call log and the OAuth door built on `laravel/mcp` and Passport.
The people an agent acts as, the consent screen, the «Connect an agent» and «Agent calls»
screens are `webx-ui/module-auth`; modules and their permissions are `webx-ui/module-admin` —
read their guides for those.

## What it owns

- **Server** `WebxUi\Mcp\Server\WebxServer` at `{webx-admin.api_path}/mcp` (route name
  `webx.mcp`, middleware alias `webx.mcp-auth`) and locally as `php artisan mcp:start webx`.
  Its `instructions` tell the agent: tools are `<module>_<tool>`, a mutating tool takes
  `dry_run: true`, writing needs `<module>:write`, it acts as the connecting administrator, read
  a module's resources before writing; and, when a module serves `settings://content-rules`
  (`WebxServer::CONTENT_RULES`, from `webx-ui/module-settings`), read the site's content rules
  before writing anything a visitor will read. `tools/list` pages by 100.
- **Contract** `ProvidesMcpTools` (`mcpTools()`, `mcpResources()`, `mcpPrompts()`), with the
  trait `ProvidesMcpDefaults` for the parts a module leaves empty. A module class that implements
  it is picked up by `ToolRegistry` from the module registry; there is no other list.
- **Building blocks** `Tool::read()` and `Tool::mutating()`, `McpResource` (`uri`, `name`,
  `description`, handler), `Prompt`. A refusal the agent should read is
  `WebxUi\Mcp\Exceptions\ToolFailure`.
- **Scopes**: `<module>:read` / `<module>:write` by default; an OAuth token carries `mcp:use`,
  which passes every scope and leaves the decision to the administrator's permissions.
- **Permissions**: a read tool is behind `<module>.view` or `<module>.manage`, a mutating one
  behind `<module>.manage`, unless the tool names its own.
- **Tables** `mcp_grants` (one per administrator and client: `read_only`, `revoked_at`) and
  `mcp_calls` (every call, refusals and `dry_run` included).
- **Commands** `webx:mcp-tools` (`--module=`), `webx:mcp:prune-calls` (scheduled daily).

## Change it without forking

| You want                                     | Do this                                                                                     |
| -------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Tools for a module of your own               | the module class implements `ProvidesMcpTools`; `Tool::read()` / `Tool::mutating()`         |
| A tool behind another permission or scope    | `permission:` (one name or a list, any of them) / `scope:` on `Tool::read()` / `mutating()` |
| Serve the HTTP server elsewhere / not at all | `WEBX_MCP_PATH=...` / `'path' => false` in `config/webx-mcp.php`                            |
| Another name than the site's host            | `WEBX_MCP_NAME=...`                                                                         |
| Other middleware or guard on the door        | `'middleware'` / `WEBX_MCP_GUARD`                                                           |
| No stdio server                              | `'local' => null`                                                                           |
| A client that cannot connect                 | add its callback to `'oauth' => ['redirect_domains' / 'custom_schemes']`                    |
| OAuth off, or under another prefix           | `WEBX_MCP_OAUTH=false` / `WEBX_MCP_OAUTH_PREFIX`                                            |
| Rate limits of registration and token        | `WEBX_MCP_OAUTH_REGISTER_THROTTLE`, `WEBX_MCP_OAUTH_TOKEN_THROTTLE` (`attempts,minutes`)    |
| Token lifetime                               | `'oauth' => ['access_token_hours', 'refresh_token_days']`                                   |
| Call log off, or kept longer                 | `WEBX_MCP_CALL_LOG=false` / `'calls' => ['days' => ...]` (null keeps forever)               |
| Another consent page                         | `php artisan vendor:publish --tag=webx-mcp-views`, or `Passport::authorizationView()`       |
| Publish the config                           | `php artisan vendor:publish --tag=webx-mcp-config`                                          |

## Do not

- Do not edit anything in `vendor/webx-ui/mcp`. Every row above is the supported way; if none
  fits, the package is missing a seam — say so instead of working around it.
- Do not register tools anywhere but a module's `mcpTools()`: the server reads only the module
  registry, and a tool added elsewhere is never listed.
- Do not prefix tool names with the module id: the registry adds `<module>_` itself, so `get`,
  not `seo_get`. Names are lowercase letters, digits and underscores; two equal full names throw.
- Do not build a changing tool with `Tool::read()`: only `Tool::mutating()` gets `dry_run`, the
  `:write` scope and the `.manage` permission, and a read-only connection lets a read tool through.
- Do not ignore `dry_run` in a mutating handler: the server passes it, the handler must honour
  it (`$tool->isDryRun($arguments)`) and report what would change without changing it.
- Do not check scopes or permissions in a handler: both are checked before it runs, and
  `tools/list` already hides what the caller may not use.
- Do not set `redirect_domains` to `['*']` or to your own domain: these are the clients'
  callback addresses; a wildcard lets anybody's client take the code.
- Do not delete `mcp_calls` rows by hand to shorten the log: set `calls.days` and run
  `webx:mcp:prune-calls`.

## Check your work

- `php artisan webx:mcp-tools --module=<id>` — name, scope, permission, whether it changes data.
- With an agent: the tool appears in `tools/list`; a mutating call with `dry_run: true` reports
  and changes nothing; the call shows in the panel's agent call log.
- The HTTP door without a token answers 401, not 500 — a 500 means `php artisan passport:keys`
  was not run.
- `php artisan webx:doctor` — what is misconfigured on the site.

## Read more

- [README.md](README.md) in this directory — declaring tools, the registry, the server, config.
- Guide: https://webx-ui.github.io/webx-ui/guide/agents
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MCP_ACCESS.md
