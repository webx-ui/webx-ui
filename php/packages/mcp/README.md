# webx-ui/mcp

How a [WebX UI](https://github.com/webx-ui/webx-ui) admin module offers itself to an AI agent.

Every module declares the tools, resources and prompts it has; this package collects them from
the installed modules and hands over one list. A panel therefore exposes exactly the tools of
the modules it actually has.

## Requirements

- PHP 8.3+
- Laravel 13
- `webx-ui/admin`

## Install

```bash
composer require webx-ui/mcp
php artisan webx:mcp-tools
```

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
                'get_seo',
                'Read the SEO fields of one entity.',
                fn (array $arguments) => $this->seo->for($arguments['id']),
                [
                    'properties' => ['id' => ['type' => 'string']],
                    'required' => ['id'],
                ],
            ),

            Tool::mutating(
                'bulk_update_seo',
                'Apply a title template to every entity matching a filter.',
                fn (array $arguments) => $this->seo->applyTemplate($arguments),
                ['properties' => ['template' => ['type' => 'string']]],
            ),
        ];
    }
}
```

CRUD is the floor, not the goal. The tools worth writing are the ones that answer a question a
person would otherwise answer by clicking for an hour: bulk edits, audits of what is missing or
duplicated, "what changed this week", drafts for a human to approve.

## Why two constructors instead of one

`Tool::mutating()` is not a flag on `Tool::read()` — the difference is the whole safety story.

A mutating tool is given a `dry_run` argument whether its author remembered one or not, so any
change can be asked about before it happens. It also gets `<module>:write` as its scope, where a
read tool gets `<module>:read`, which is what a token is checked against.

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
$registry->tool('seo_get_seo');  // by the name an agent uses
$registry->scopes();             // ['media:audit', 'seo:read', 'seo:write']
```

A tool's public name is `<module>_<tool>`, with dashes in the module id turned into underscores.
Two modules cannot end up with the same one; the registry refuses rather than letting one shadow
the other.

```bash
php artisan webx:mcp-tools --module=seo
```

## What is not here yet

The transport. `laravel/mcp` is the intended way to serve these over Streamable HTTP, and it is
still pre-1.0 — so this package defines the contracts, which the modules are about to depend on,
and leaves the endpoint for when that API settles. Nothing about a module's declaration will
have to change when it arrives.

Authorisation, the call log and the queue of changes awaiting approval in the panel are part of
the same later step.

## Licence

MIT.
