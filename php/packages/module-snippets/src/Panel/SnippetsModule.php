<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Snippets\Demo\SnippetsDemo;
use WebxUi\Snippets\Mcp\SnippetsResources;
use WebxUi\Snippets\Mcp\SnippetsTools;

/**
 * The section "Codes" (§7): the codes of outside services by place, dragged into order, edited
 * with highlighting, switched on and off.
 *
 * One permission, `snippets.manage` (§8): reading a code is reading somebody's service keys, and
 * writing one is running JavaScript on every page for every visitor — there is no harmless half
 * to give out. No role gets it when the module is installed, so only a super administrator sees
 * the section until somebody grants it on purpose.
 *
 * In the group `system`, beside the settings and SEO (decision 7): a rare, technical thing.
 *
 * To an agent it is the same section by other doors (§9): six tools `snippets_*` behind
 * `snippets:read` and `snippets:write` and the same permission, `snippets://catalog` and
 * `snippets://places` to read first. The demo is one code, switched off.
 */
final class SnippetsModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel. */
    public const ID = 'snippets';

    public const PERMISSION = 'snippets.manage';

    public function __construct(
        private readonly SnippetsTools $tools,
        private readonly SnippetsResources $resources,
        private readonly SnippetsDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-snippets::module.title');
    }

    public function icon(): string
    {
        return 'file-html';
    }

    public function order(): int
    {
        return 850;
    }

    public function group(): string
    {
        return 'system';
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [self::PERMISSION];
    }

    /**
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
}
