<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\ProvidesDemo;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Notifications\Demo\NotificationsDemo;
use WebxUi\Notifications\Mcp\NotificationsResources;
use WebxUi\Notifications\Mcp\NotificationsTools;

/**
 * The section "Notifications" (§10): channels, and the log of what they sent.
 *
 * Two permissions (§11, decision 14): `notifications.view` reads the channels and the log — the
 * log holds what visitors typed into forms — and `notifications.manage` changes channels, sends
 * tests, searches chats and retries. No role gets them when the module is installed, so only a
 * super administrator sees the section until somebody grants them on purpose.
 *
 * In the group `system`, beside the settings and the codes: set up once, rarely visited.
 *
 * To an agent it is the same section by other doors (§12): eleven tools `notifications_*` behind
 * `notifications:read` and `notifications:write` and the same two permissions, and
 * `notifications://providers`, `notifications://events`, `notifications://channels` to read first.
 * The demo is one e-mail channel, switched off.
 */
final class NotificationsModule extends AbstractModule implements ProvidesDemo, ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    /** The id in the panel. */
    public const ID = 'notifications';

    public const VIEW = 'notifications.view';

    public const MANAGE = 'notifications.manage';

    public function __construct(
        private readonly NotificationsTools $tools,
        private readonly NotificationsResources $resources,
        private readonly NotificationsDemo $demo,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-notifications::module.title');
    }

    public function icon(): string
    {
        return 'bell';
    }

    public function order(): int
    {
        return 860;
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
        return [self::VIEW, self::MANAGE];
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
