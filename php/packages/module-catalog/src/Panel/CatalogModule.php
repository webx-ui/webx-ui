<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\Catalog\Mcp\CatalogResources;
use WebxUi\Catalog\Mcp\CatalogTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;
use WebxUi\Mcp\Tool;

/**
 * The catalogue as a section of the panel: products, categories and «Deleted», in a group of its
 * own that the satellites join — brands, stock, labels stand beside it, not inside it (§1).
 *
 * Three permissions (§11.5): `view` opens the section, `manage` writes everything but deletes
 * and restores, `delete` does those and opens «Deleted». Categories go by the same three: the
 * people who file products are the people who arrange the shelves.
 *
 * To an agent it is the same section by other doors (§12): fifteen tools behind the same three
 * permissions, and six resources to read before writing. The scopes are `catalog:read` and
 * `catalog:write`.
 */
final class CatalogModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    /** The id of the navigation group the satellites join as well. */
    public const GROUP = 'catalog';

    public function __construct(
        private readonly CatalogTools $tools,
        private readonly CatalogResources $resources,
        private readonly CatalogDemo $demo,
    ) {}

    public function id(): string
    {
        return 'catalog';
    }

    public function title(): string
    {
        return (string) __('webx-catalog::module.title');
    }

    public function icon(): string
    {
        return 'cart';
    }

    public function order(): int
    {
        return 300;
    }

    public function group(): string
    {
        return self::GROUP;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['catalog.view', 'catalog.manage', 'catalog.delete'];
    }

    /**
     * Nothing: the pictures of the demo products are read from `module-media`'s package files and
     * go into the galleries, not the library.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return [];
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
        return [];
    }
}
