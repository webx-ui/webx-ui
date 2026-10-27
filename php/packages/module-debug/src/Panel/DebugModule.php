<?php

declare(strict_types=1);

namespace WebxUi\Debug\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Debug\Mcp\DebugTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * The debug bar as a module of the panel: one permission and one MCP tool, no screen.
 *
 * `debug.view` is given to no role on install (decision 5 of the spec): a report shows the values
 * of the page's queries, and those are the visitors' personal data — so only a super
 * administrator has it until somebody grants it on purpose. Not registered in production, unless
 * the site opens `debug_page` there (`WEBX_DEBUG_MCP_IN_PRODUCTION`, §3.3 of the spec).
 */
final class DebugModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'debug';

    public const PERMISSION = 'debug.view';

    public function __construct(private readonly DebugTools $tools) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-debug::module.title');
    }

    public function icon(): string
    {
        return 'monitor';
    }

    public function order(): int
    {
        return 990;
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
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }
}
