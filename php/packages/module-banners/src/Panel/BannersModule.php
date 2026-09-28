<?php

declare(strict_types=1);

namespace WebxUi\Banners\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Banners\Demo\BannersDemo;
use WebxUi\Banners\Mcp\BannerResources;
use WebxUi\Banners\Mcp\BannerTools;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;

/**
 * Where the banners are put into places, ordered and turned on (§5.4): one entry of the menu, at
 * the top level, after the team.
 *
 * The panel's usual pair: `banners.view` opens the places and their banners, `banners.manage`
 * writes — the banners, their order and the places of somebody's own (§5.5).
 *
 * To an agent it is the same section by other doors (§5.7): nine tools behind `banners:read` and
 * `banners:write`, and `banners://catalog` to read first.
 */
final class BannersModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel — and the name `webx:setup` knows it by. */
    public const ID = 'banners';

    public function __construct(
        private readonly BannerTools $tools,
        private readonly BannerResources $resources,
        private readonly BannersDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-banners::module.banners');
    }

    public function icon(): string
    {
        return 'image';
    }

    public function order(): int
    {
        return 660;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['banners.view', 'banners.manage'];
    }

    /**
     * The library always, for the pictures; the pages when installed, for a button to point at.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->demo->requires();
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
