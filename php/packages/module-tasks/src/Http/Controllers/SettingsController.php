<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Settings\Settings;
use WebxUi\Tasks\Notifications\NoticeFailed;
use WebxUi\Tasks\Notifications\Notices;
use WebxUi\Tasks\Notifications\NotificationSettings;
use WebxUi\Tasks\Notifications\Notifier;

/**
 * The section's notification settings (§6a): the screen `tasks.settings`, saved into the site's
 * settings under `tasks.*`, and the button that sends a test message.
 *
 * The bot's token goes in through the screen's password field and never comes out: what is read
 * back is whether one is set. An empty field leaves the token as it was; `forget_token` removes it.
 */
final class SettingsController
{
    /** The field the token is typed into — a door in, not a stored value. */
    private const TOKEN_FIELD = 'tasks.telegram.token';

    public function __construct(
        private readonly Settings $settings,
        private readonly NotificationSettings $notifications,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data($this->state());
    }

    public function update(Request $request, ScreenValues $values): JsonResponse
    {
        $input = $request->input('values');
        $input = is_array($input) ? $input : [];
        $token = $input[self::TOKEN_FIELD] ?? null;
        unset($input[self::TOKEN_FIELD]);

        $user = $request->user();
        $stored = $values->validate(
            NotificationSettings::SCREEN,
            $input,
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
        );

        unset($stored[self::TOKEN_FIELD]);
        $this->settings->save($stored);

        if ($request->boolean('forget_token')) {
            $this->notifications->setToken(null);
        } elseif (is_string($token) && trim($token) !== '') {
            $this->notifications->setToken($token);
        }

        return ApiResponse::data($this->state());
    }

    /** One message through the channel, now, to see that the token and the chats are right. */
    public function test(Notifier $notifier, Notices $notices): JsonResponse
    {
        if (! $notifier->configured()) {
            return ApiResponse::message((string) __('webx-tasks::errors.notify-not-configured'), 409);
        }

        try {
            $notifier->send($notices->test((string) config('app.name')));
        } catch (NoticeFailed $failure) {
            return ApiResponse::message($failure->getMessage(), 409);
        }

        return ApiResponse::message((string) __('webx-tasks::panel.test-sent'));
    }

    /**
     * The values the screen shows — every switch with its default filled in — and whether a token
     * is set. Never the token.
     *
     * @return array{values: array<string, mixed>, token_set: bool}
     */
    private function state(): array
    {
        $values = [
            self::TOKEN_FIELD => '',
            NotificationSettings::CHATS => implode("\n", $this->notifications->chats()),
            NotificationSettings::STALE_HOURS => $this->notifications->staleHours(),
        ];

        foreach (NotificationSettings::EVENTS as $event => $key) {
            $values[$key] = $this->notifications->enabled($event);
        }

        return ['values' => $values, 'token_set' => $this->notifications->hasToken()];
    }
}
