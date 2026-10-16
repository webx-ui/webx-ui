<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * The section "Notifications" (§10): channels, and the log of what they sent.
 *
 * Two permissions (§11, decision 14): `notifications.view` reads the channels and the log — the
 * log holds what visitors typed into forms — and `notifications.manage` changes channels, sends
 * tests, searches chats and retries. No role gets them when the module is installed, so only a
 * super administrator sees the section until somebody grants them on purpose.
 *
 * In the group `system`, beside the settings and the codes: set up once, rarely visited.
 */
final class NotificationsModule extends AbstractModule
{
    /** The id in the panel. */
    public const ID = 'notifications';

    public const VIEW = 'notifications.view';

    public const MANAGE = 'notifications.manage';

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
}
