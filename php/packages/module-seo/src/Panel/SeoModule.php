<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Seo\Demo\SeoDemo;
use WebxUi\Seo\Mcp\SeoTools;

/**
 * The panel section for SEO that belongs to no entity: rules written for addresses, addresses
 * that have moved, and what the site says about itself when nothing else does.
 *
 * Sits above the settings in the system group, because it is the one an editor opens weekly and
 * the settings are the one they open twice.
 */
final class SeoModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public function __construct(private readonly SeoDemo $demo) {}

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
     * The blog: the demo rule is a mask over its addresses, and a rule for a shape of address
     * nothing answers would be a rule nobody can see working (§9).
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return ['articles'];
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
        return SeoTools::all();
    }
}
