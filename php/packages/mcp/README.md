# webx-ui/mcp

How a [WebX UI](https://github.com/webx-ui/webx-ui) admin module offers itself to an AI agent,
and the server that serves what they offer.

Every module declares the tools, resources and prompts it has; this package collects them from
the installed modules and serves the lot as one MCP server over Streamable HTTP and over stdio.
A panel therefore exposes exactly the tools of the modules it actually has.

## Requirements

- PHP 8.3+
- Laravel 13
- `webx-ui/module-admin`; `laravel/mcp` comes with this package

## Install

```bash
composer require webx-ui/mcp
php artisan webx:mcp-tools
```

The server answers at `{api_path}/mcp` — `/api/cms/mcp` by default — as soon as the package is
installed. It is closed until a token opens it:

```bash
php artisan vendor:publish --tag=sanctum-migrations && php artisan migrate   # once per site
php artisan webx:mcp:token admin@example.com --name=claude
```

That prints a Sanctum token of the administrator, once, with every scope on offer as its
abilities (`--scopes=blocks:read,blocks:write` to give fewer), and the line that connects
Claude Code to it. An agent then acts as that administrator, within those scopes.

A local agent on the machine the site runs on can skip the token: `php artisan mcp:start webx`
is the same server over stdio, trusted the way tinker is trusted.

## Declaring tools

```php
use WebxUi\Admin\AbstractModule;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

class SeoModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults; // nothing to say about resources or prompts yet

    public function id(): string
    {
        return 'seo';
    }

    public function mcpTools(): array
    {
        return [
            Tool::read(
                'get',
                'Read the SEO fields of one entity.',
                fn (array $arguments) => $this->seo->for($arguments['id']),
                [
                    'properties' => ['id' => ['type' => 'string']],
                    'required' => ['id'],
                ],
            ),

            Tool::mutating(
                'bulk_update',
                'Apply a title template to every entity matching a filter.',
                fn (array $arguments, ?Authenticatable $user) => $this->seo->applyTemplate($arguments, by: $user),
                ['properties' => ['template' => ['type' => 'string']]],
            ),
        ];
    }
}
```

CRUD is the floor, not the goal. The tools worth writing are the ones that answer a question a
person would otherwise answer by clicking for an hour: bulk edits, audits of what is missing or
duplicated, "what changed this week", drafts for a human to approve.

A handler gets the arguments as the agent sent them and the administrator the call acts as —
null on the stdio server, where there is no request. What it returns is what the agent reads: a
map goes out as structured content with its JSON as text, a string as it is. A refusal the agent
should read — "no such block", "the template failed on line 12" — is a thrown
`WebxUi\Mcp\Exceptions\ToolFailure`; any other exception is reported and, outside debug mode,
reaches the agent as "something went wrong".

## Why two constructors instead of one

`Tool::mutating()` is not a flag on `Tool::read()` — the difference is the whole safety story.

A mutating tool is given a `dry_run` argument whether its author remembered one or not, so any
change can be asked about before it happens. It also gets `<module>:write` as its scope, where a
read tool gets `<module>:read`, which is what a token is checked against — before the handler
runs, so no handler has to ask who is calling.

```php
$tool->isDryRun($arguments); // the handler decides what to do about it
```

A tool without a description is refused, and so is a name a model could not call — the name goes
to the agent verbatim, so it must be lowercase letters, digits and underscores.

## Reading the registry

```php
use WebxUi\Mcp\Registry\ToolRegistry;

$registry->tools();              // every tool, as BoundTool
$registry->toolsOf('seo');       // one module's
$registry->tool('seo_get');      // by the name an agent uses
$registry->scopes();             // ['media:audit', 'seo:read', 'seo:write']
```

A tool's public name is `<module>_<tool>`, with dashes in the module id turned into underscores
— so a module names its tools short (`get`, not `get_seo`). Two modules cannot end up with the
same one; the registry refuses rather than letting one shadow the other.

```bash
php artisan webx:mcp-tools --module=seo
```

## The server

`WebxUi\Mcp\Server\WebxServer` is a `laravel/mcp` server that declares nothing of its own: when
it starts it reads the registry and wraps every tool, resource and prompt in the class the
transport expects. The module's JSON Schema goes out as written; a read tool is announced with
`readOnlyHint`, so a client may skip its confirmation for it.

`tools/list` answers with a hundred at a time rather than the default fifteen. A panel with six
modules offers more than forty tools, and a client that does not follow the cursor would see a
third of them and conclude the rest do not exist.

`config/webx-mcp.php`:

| Key          | Default             | What it is                                                       |
| ------------ | ------------------- | ---------------------------------------------------------------- |
| `path`       | `{api_path}/mcp`    | Where the HTTP server answers; `false` for none                  |
| `middleware` | `['webx.mcp-auth']` | What guards it                                                   |
| `guard`      | `sanctum`           | The guard `webx.mcp-auth` asks for a user                        |
| `local`      | `webx`              | The name of the stdio server (`mcp:start webx`); `null` for none |

`webx.mcp-auth` answers 401 as JSON without a user, 403 for an administrator switched off since
the token was issued, and makes the guard the default so that `$request->user()` in a tool is
this administrator. Any other middleware — Passport's `auth:api` with `Mcp::oauthRoutes()`,
your own — goes in its place.

A scope is an ability of the token. A caller without a token — the stdio server, or a person
signed in through a session — is not asked for one: the middleware that let them in decides.

## What is not here yet

A call log, a queue of changes awaiting approval in the panel, and a screen for the tokens. The
token is issued from the console on purpose: it is a key to everything its scopes name, and
handing one out is a decision for the person who can already run artisan on the server.

## Licence

MIT.
