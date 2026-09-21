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
installed, and it is closed: without a token it answers 401 and says where to get one.

A local agent on the machine the site runs on needs none: `php artisan mcp:start webx` is the
same server over stdio, trusted the way tinker is trusted.

## Letting a person connect their agent

Anyone else connects over OAuth. Passport does the issuing; it comes with `webx-ui/module-auth`,
and a site switches it on once:

```bash
php artisan vendor:publish --tag=passport-migrations && php artisan migrate
php artisan passport:keys
```

Without the keys the guard cannot be built at all, and a call with no token answers 500 where it
should answer 401 — so this is a deployment step, not an optional one.

From then on the address is all a person needs, and it carries nothing secret:

```
https://example.com/api/cms/mcp
```

Pasted into Claude, ChatGPT or `claude mcp add --transport http webx <address>`, the client
discovers the authorization server from the 401, registers itself, and sends the person to the
panel to sign in and agree. What it leaves with is a token of theirs — so the agent acts as that
administrator, and switching the account off ends its access the same minute.

Two things ship open and this package closes them: `config('mcp.redirect_domains')` is `['*']` by
default, which would let anybody register a client that takes the code to their own server, and
client registration has no rate limit. Both are in `webx-mcp.oauth`, and a client whose redirect
address is not on the list cannot connect until it is added.

Keys for machines — CI, scripts, anything with no browser to open — are not here yet. The console
command that used to print one went with Sanctum.

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

The same split names the panel permission the tool is behind: a mutating tool needs
`<module>.manage`, a read tool `<module>.view` — or `<module>.manage`, because somebody who may
edit may look, the way the module's own routes let them. A module whose permissions are not named
after its id says so on the tool, with one name or several that mean "any of these":

```php
Tool::read('list', '…', $handler, permission: ['blog.articles.view', 'blog.taxonomy.manage']);
Tool::mutating('merge', '…', $handler, permission: 'blog.taxonomy.manage');
```

Both are checked in the same place as the scope, before the handler; and `tools/list` leaves out
what the caller may not use, so an agent is never shown a tool only to be refused it.

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
| `guard`      | `api`               | The guard `webx.mcp-auth` asks for a user                        |
| `oauth`      | see below           | How a person connects their agent; ignored without Passport      |
| `calls`      | see below           | The call log: on, kept 90 days, arguments cut to 4000 characters |
| `local`      | `webx`              | The name of the stdio server (`mcp:start webx`); `null` for none |

`webx.mcp-auth` answers 401 as JSON without a user, 403 for an administrator switched off since
the token was issued, and makes the guard the default so that `$request->user()` in a tool is
this administrator. Any other middleware goes in its place.

The `api` guard is registered for you — Passport's driver over `webx-auth.provider`, the panel's
own people — unless the application has already defined one under that name.

`webx-mcp.oauth` has `guard` (whom Passport asks at the consent screen: the panel's people, not
the site's visitors), `redirect_domains` and `custom_schemes` (what `config/mcp.php` is set to),
`register_throttle`, and how long a token and its refresh last — an hour and a month.

A scope is what the token carries. A token issued over OAuth carries one for the whole server,
`mcp:use`, because that is the only scope a client is ever offered there: it says an agent may
talk to the panel and nothing about which module, and what limits the call is the
administrator's own permissions. A token that names module scopes is read scope by scope. A
caller without a token at all — the stdio server, or a person signed in through a session — is
not asked for one: the middleware that let them in decides.

The permissions are the administrator's own, as the panel checks them (`HasPermissions` from
`webx-ui/module-admin`): an agent let in by an editor can do what that editor can do, and its
`tools/list` is already that — a narrower role sees a shorter list. On the stdio server there is
nobody to ask, so everything is listed. "Read only" on the consent screen sits above both: it
refuses every tool that writes, whatever the person may do themselves.

## The call log

Every tool call lands in `mcp_calls`: who the agent acted as (`cms_user_id`), on which
connection (`grant_id`), which tool, the arguments, whether it was a dry run, whether it was
answered or refused and with what words, and how long it took. It is written in one place,
around the whole of the call — so a refusal at the door for a scope, a read-only connection or
a missing permission is a row, and so is what the handler threw. A handler that answers
`['ok' => false, 'reason' => …]` is written down as refused too: that is what the person
reading the log would call it.

No secret reaches it. The token and the headers are never looked at, and any argument named
like one — `password`, `token`, `api_key`, `secret`, `authorization` — is blanked before
writing. Arguments are cut to `calls.arguments_length` characters. There is deliberately no
link from a call to a page or a file: tools are about different things, and a log that
pretended otherwise would be wrong more often than useful.

`webx:mcp:prune-calls` removes rows older than `calls.days` and runs nightly on the scheduler;
`null` keeps them forever, `calls.enabled` false writes nothing. `webx-ui/module-auth` shows
the log in the panel, next to the administrators, behind `admins.audit`.

## What is not here yet

A queue of changes awaiting approval in the panel, a screen for the connections, and keys for
machines — CI and scripts, which have no browser to send anybody to.

## Licence

MIT.
