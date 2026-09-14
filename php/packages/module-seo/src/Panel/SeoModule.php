<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Seo\Mcp\SeoTools;

/**
 * The panel section for SEO that belongs to no entity: rules written for addresses, addresses
 * that have moved, and what the site says about itself when nothing else does.
 *
 * Sits above the settings in the system group, because it is the one an editor opens weekly and
 * the settings are the one they open twice.
 */
final class SeoModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function id(): string
    {
        return 'seo';
    }

    public function title(): string
    {
        return (string) __('webx-seo::module.title');
    }

    public function icon(): string
    {
        return 'search';
    }

    public function order(): int
    {
        return 700;
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
        return ['seo.view', 'seo.manage'];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return SeoTools::all();
    }
}
