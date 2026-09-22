<?php

declare(strict_types=1);

namespace WebxUi\Pages\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;
use WebxUi\Mcp\Tool;
use WebxUi\Pages\Demo\PagesDemo;
use WebxUi\Pages\Mcp\PagePrompts;
use WebxUi\Pages\Mcp\PageResources;
use WebxUi\Pages\Mcp\PageTools;

/**
 * The section where the site's pages are edited.
 *
 * In the content group and first in it: a site is its pages, and every other content module
 * that arrives later is something more specific than one. Two permissions, the panel's usual
 * pair — `view` opens the section and the picker a link field would use, `manage` writes.
 *
 * To an agent it is the same section by other doors (§13): nine tools, the sitemap to read
 * first, and one prompt. The scopes are `pages:read` and `pages:write`.
 */
final class PagesModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    public function __construct(
        private readonly PageTools $tools,
        private readonly PageResources $resources,
        private readonly PagePrompts $prompts,
        private readonly PagesDemo $demo,
    ) {}

    public function id(): string
    {
        return 'pages';
    }

    public function title(): string
    {
        return (string) __('webx-pages::module.title');
    }

    public function icon(): string
    {
        return 'file';
    }

    public function order(): int
    {
        return 200;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['pages.view', 'pages.manage'];
    }

    /**
     * The block types, because the demo pages are made of them (§9).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return ['blocks'];
    }

    public function seed(DemoLedger $ledger): void
    {
        $this->demo->seed($ledger);
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return $this->resources->all();
    }

    /**
     * @return list<Prompt>
     */
    public function mcpPrompts(): array
    {
        return $this->prompts->all();
    }
}
