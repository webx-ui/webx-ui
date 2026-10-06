<?php

declare(strict_types=1);

namespace WebxUi\Audit\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Admin\Panel\SystemSections;
use WebxUi\Audit\Mcp\AuditTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * «System → Audit»: the site checked the way an SEO, a front-end developer and an admin check
 * every new project by hand (§8). After SEO in the group — both are about being found, and the
 * audit is the one opened after a release rather than every week.
 */
final class AuditModule extends AbstractModule implements HasNavSection, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'audit';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-audit::module.title');
    }

    public function icon(): string
    {
        return 'check-circle';
    }

    public function order(): int
    {
        return 710;
    }

    public function group(): string
    {
        return 'system';
    }

    public function navSection(): string
    {
        return SystemSections::SEARCH;
    }

    /**
     * Looking, starting a run, and changing things — the fixes, and hidden findings later.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['audit.view', 'audit.run', 'audit.manage'];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return AuditTools::all();
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return AuditTools::resources();
    }
}
