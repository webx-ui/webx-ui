<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Notifications;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use WebxUi\Settings\Settings;

/**
 * What the section's settings screen says about notifications, over the defaults of
 * `config/webx-tasks.php` — read on every call, so a value saved a minute ago is the one used.
 *
 * The bot's token is the one secret: stored encrypted under its own key, outside the screen's
 * values, and never answered anywhere — the screen, the API and MCP learn only whether it is set.
 */
final class NotificationSettings
{
    /** The screen the section keeps its settings on. */
    public const SCREEN = 'tasks.settings';

    /** Where the encrypted token lives. No screen names it, so nothing that lists settings shows it. */
    public const TOKEN = 'tasks.telegram-token';

    public const CHATS = 'tasks.telegram.chats';

    public const STALE_HOURS = 'tasks.notify.stale_hours';

    /** The events of §6a by the key their switch is stored under. */
    public const EVENTS = [
        'stage.review_human' => 'tasks.notify.review_human',
        'task.done' => 'tasks.notify.task_done',
        'stage.blocked' => 'tasks.notify.blocked',
        'stage.rework' => 'tasks.notify.rework',
        'stage.review_auto' => 'tasks.notify.review_auto',
        'stage.stale' => 'tasks.notify.stale',
    ];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
        private readonly StringEncrypter $encrypter,
    ) {}

    public function enabled(string $event): bool
    {
        $key = self::EVENTS[$event] ?? null;

        if ($key === null) {
            return false;
        }

        $default = (bool) $this->config->get('webx-tasks.notify.'.substr($key, strlen('tasks.notify.')), false);

        return (bool) ($this->settings()->get($key, $default) ?? $default);
    }

    public function staleHours(): int
    {
        $default = (int) $this->config->get('webx-tasks.notify.stale_hours', 6);

        return max(1, (int) ($this->settings()->get(self::STALE_HOURS, $default) ?? $default));
    }

    /**
     * The chats to write to: one per line, or separated by commas — a person, a group, a channel.
     *
     * @return list<string>
     */
    public function chats(): array
    {
        $raw = (string) ($this->settings()->get(self::CHATS, '') ?? '');

        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $raw) ?: []), static fn (string $chat): bool => $chat !== ''));
    }

    public function token(): ?string
    {
        $stored = $this->settings()->get(self::TOKEN);

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return $this->encrypter->decryptString($stored);
        } catch (DecryptException) {
            // Written under another APP_KEY: as good as never set, and the screen says so.
            return null;
        }
    }

    public function hasToken(): bool
    {
        return $this->token() !== null;
    }

    public function setToken(?string $token): void
    {
        $token = $token === null ? '' : trim($token);

        if ($token === '') {
            $this->settings()->clear([self::TOKEN]);

            return;
        }

        $this->settings()->save([self::TOKEN => $this->encrypter->encryptString($token)]);
    }

    private function settings(): Settings
    {
        return $this->container->make(Settings::class);
    }
}
