<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Admin\AbstractModule;
use WebxUi\Blocks\Mcp\BlockPrompts;
use WebxUi\Blocks\Mcp\BlockResources;
use WebxUi\Blocks\Mcp\BlockTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Prompt;
use WebxUi\Mcp\Tool;

/**
 * The section where a block type is made.
 *
 * At the top level next to the media library, because it is content tooling rather than a
 * system setting — and the ones who use it are the ones who build the pages. Two permissions,
 * the panel's usual pair: `view` opens the section and the picker, `manage` writes. Saving a
 * block is running Blade, so `manage` is given the way a shell is given (§16).
 *
 * To an agent the module is the same section by other doors (§18): the tools, the resources
 * it should read first, and one prompt. The scopes are `blocks:read` and `blocks:write`.
 */
final class BlocksModule extends AbstractModule implements ProvidesMcpTools
{
    public function __construct(
        private readonly Config $config,
        private readonly BlockTools $tools,
        private readonly BlockResources $resources,
        private readonly BlockPrompts $prompts,
    ) {}

    public function id(): string
    {
        return 'blocks';
    }

    public function title(): string
    {
        return (string) __('webx-blocks::module.title');
    }

    public function icon(): string
    {
        return 'grid';
    }

    public function order(): int
    {
        return 250;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['blocks.view', 'blocks.manage'];
    }

    /**
     * What the front end needs before it opens the section: the groups a type may belong to,
     * in order; whether writing is allowed at all on this site; and what the site's own bundle
     * provides to a block's script.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $groups = $this->config->get('webx-blocks.groups', []);
        $provides = $this->config->get('webx-blocks.provides', []);

        return [
            'groups' => is_array($groups) ? array_values(array_map('strval', $groups)) : [],
            'editing' => (bool) $this->config->get('webx-blocks.editing', true),
            'provides' => is_array($provides) ? array_values(array_map('strval', $provides)) : [],
        ];
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
