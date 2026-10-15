<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Notifications;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Throwable;

/**
 * Telegram, the channel that comes with the module: the Bot API's `sendMessage`, plain text, to
 * every chat on the settings screen.
 *
 * Plain text rather than Markdown on purpose: a report written by an agent is full of characters
 * Telegram's Markdown would choke on, and a message refused for a stray underscore is a message
 * nobody gets. A failure names the chat and Telegram's own words, never the token — the address
 * carries it, and the journal is read by people who are not meant to have it.
 */
final class TelegramNotifier implements Notifier
{
    public function __construct(
        private readonly NotificationSettings $settings,
        private readonly Http $http,
        private readonly Config $config,
    ) {}

    public function configured(): bool
    {
        return $this->settings->hasToken() && $this->settings->chats() !== [];
    }

    public function send(Notice $notice): void
    {
        $token = $this->settings->token();
        $chats = $this->settings->chats();

        if ($token === null || $chats === []) {
            throw new NoticeFailed('Telegram is not set up: the bot token or the chats are missing.');
        }

        $url = rtrim((string) $this->config->get('webx-tasks.telegram.api'), '/')."/bot{$token}/sendMessage";
        $failed = [];

        foreach ($chats as $chat) {
            try {
                $response = $this->http
                    ->timeout((int) $this->config->get('webx-tasks.telegram.timeout', 10))
                    ->asJson()
                    ->post($url, [
                        'chat_id' => $chat,
                        'text' => mb_substr($notice->text, 0, 4000),
                        'link_preview_options' => ['is_disabled' => true],
                    ]);

                if (! $response->successful() || $response->json('ok') !== true) {
                    $failed[] = "{$chat}: ".((string) ($response->json('description') ?? 'HTTP '.$response->status()));
                }
            } catch (Throwable $exception) {
                $failed[] = "{$chat}: ".str_replace($token, '***', $exception->getMessage());
            }
        }

        if ($failed !== []) {
            throw new NoticeFailed('Telegram did not take the message — '.implode('; ', $failed));
        }
    }
}
