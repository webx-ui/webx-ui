<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Telegram;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Throwable;
use WebxUi\Notifications\Contracts\FindsRecipients;
use WebxUi\Notifications\Contracts\Provider;
use WebxUi\Notifications\Refusal;
use WebxUi\Notifications\Sending\CheckResult;
use WebxUi\Notifications\Sending\Credentials;
use WebxUi\Notifications\Sending\DeliveryFailed;
use WebxUi\Notifications\Sending\Field;
use WebxUi\Notifications\Sending\FoundRecipient;
use WebxUi\Notifications\Sending\Message;
use WebxUi\Notifications\Sending\Recipient;

/**
 * A Telegram bot writing to chats (§6.1): the Bot API's `sendMessage` in HTML, through Laravel's
 * HTTP client, so tests fake it with `Http::fake()`.
 *
 * The token is in the address of every request, so whatever an exception says goes through
 * {@see Credentials::mask()} before anybody reads it. A message longer than Telegram takes is
 * sent as plain text cut to the limit — cutting HTML could leave a tag open, and Telegram refuses
 * the whole message for that.
 */
final class TelegramProvider implements FindsRecipients, Provider
{
    public const KEY = 'telegram';

    /** A numeric chat id (groups are negative) or a public channel's @username. */
    private const CHAT = '/^(-?\d{1,20}|@[A-Za-z][A-Za-z0-9_]{4,31})$/';

    public function __construct(
        private readonly Http $http,
        private readonly Config $config,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'webx-notifications::providers.telegram.label';
    }

    public function icon(): string
    {
        return 'paper-plane-tilt';
    }

    public function fields(): array
    {
        return [new Field('token', 'webx-notifications::providers.telegram.token', secret: true)];
    }

    public function hasSubject(): bool
    {
        return false;
    }

    public function address(): string
    {
        return 'telegram';
    }

    public function limit(): int
    {
        return 4096;
    }

    public function recipients(array $recipients): array
    {
        $chats = [];

        foreach ($recipients as $recipient) {
            if (is_string($recipient) || is_int($recipient)) {
                $recipient = ['chat_id' => $recipient];
            }

            if (! is_array($recipient)) {
                throw new Refusal($this->say('recipients-shape'));
            }

            if (isset($recipient['variable'])) {
                $chats[] = ['variable' => $recipient['variable']];

                continue;
            }

            $id = $recipient['chat_id'] ?? null;
            $id = is_int($id) ? (string) $id : (is_string($id) ? trim($id) : '');

            if (preg_match(self::CHAT, $id) !== 1) {
                throw new Refusal($this->say('telegram.chat', ['chat' => $id]));
            }

            $label = $recipient['label'] ?? null;
            $label = is_string($label) && trim($label) !== '' ? mb_substr(trim($label), 0, 255) : null;

            // The same chat twice keeps the words somebody gave it.
            if (! isset($chats[$id]) || $label !== null) {
                $chats[$id] = ['chat_id' => $id] + ($label === null ? [] : ['label' => $label]);
            }
        }

        return array_values($chats);
    }

    public function resolve(array $recipients, array $variables): array
    {
        $resolved = [];

        foreach ($recipients as $recipient) {
            if (isset($recipient['variable'])) {
                $name = (string) $recipient['variable'];
                $value = $variables[$name] ?? '';
                $value = is_string($value) ? trim($value) : '';

                $resolved[] = preg_match(self::CHAT, $value) === 1
                    ? new Recipient($value, '{{'.$name.'}}')
                    : Recipient::skipped('{{'.$name.'}}', 'no_address');

                continue;
            }

            $label = $recipient['label'] ?? null;
            $resolved[] = new Recipient((string) $recipient['chat_id'], is_string($label) ? $label : null);
        }

        return $resolved;
    }

    public function missing(array $options, array $credentialsSet): ?string
    {
        return in_array('token', $credentialsSet, true) ? null : 'missing-token';
    }

    public function checkTemplate(string $template): array
    {
        $words = [];

        foreach (TelegramMarkup::problems($template) as [$problem, $tag]) {
            $words[] = $this->say('telegram.markup-'.$problem, [
                'tag' => $tag,
                'allowed' => implode(', ', array_keys(TelegramMarkup::TAGS)),
            ]);
        }

        return array_values(array_unique($words));
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
    }

    public function plain(string $body): string
    {
        return TelegramMarkup::plain($body);
    }

    public function send(Message $message, Credentials $credentials, array $options, Recipient $to): void
    {
        $token = $credentials->get('token') ?? throw DeliveryFailed::permanent($this->say('missing-token'));

        $body = $message->truncated
            ? ['text' => $message->plain]
            : ['text' => $message->body, 'parse_mode' => 'HTML'];

        $response = $this->call($credentials, 'post', $this->url($token, 'sendMessage'), [
            'chat_id' => $to->key,
            'link_preview_options' => ['is_disabled' => true],
            ...$body,
        ]);

        if (! $response->successful() || $response->json('ok') !== true) {
            throw $this->failure($response, $credentials);
        }
    }

    public function check(Credentials $credentials, array $options): CheckResult
    {
        $token = $credentials->get('token');

        if ($token === null) {
            return new CheckResult(false, $this->say('missing-token'));
        }

        try {
            $response = $this->call($credentials, 'get', $this->url($token, 'getMe'));
        } catch (DeliveryFailed $failed) {
            return new CheckResult(false, $failed->getMessage());
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            return new CheckResult(false, $this->failure($response, $credentials)->getMessage());
        }

        $username = (string) $response->json('result.username');

        return new CheckResult(true, $this->say('telegram.bot', ['bot' => '@'.$username]), ['bot' => '@'.$username]);
    }

    /**
     * The chats in the bot's recent updates (§6.3). Without `offset`, so nothing is confirmed and
     * whoever else reads the bot still gets them; a bot with a webhook gives no updates at all,
     * and the webhook is not taken down — the bot may be working somewhere else.
     */
    public function find(Credentials $credentials): array
    {
        $token = $credentials->get('token') ?? throw DeliveryFailed::permanent($this->say('missing-token'));

        $response = $this->call($credentials, 'get', $this->url($token, 'getUpdates'), [
            'allowed_updates' => json_encode(['message', 'channel_post', 'my_chat_member']),
        ]);

        if ($response->status() === 409) {
            throw DeliveryFailed::permanent($this->say('telegram.webhook'));
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            throw $this->failure($response, $credentials);
        }

        $chats = [];

        foreach ((array) $response->json('result', []) as $update) {
            if (! is_array($update)) {
                continue;
            }

            foreach (['message', 'edited_message', 'channel_post', 'my_chat_member'] as $part) {
                $chat = $update[$part]['chat'] ?? null;

                if (is_array($chat) && isset($chat['id'])) {
                    $chats[(string) $chat['id']] = $this->found($chat);
                }
            }
        }

        return array_values($chats);
    }

    public function screen(): array
    {
        $visible = ['when' => 'provider', 'is' => self::KEY];

        $nodes = [
            [
                'id' => 'token',
                'type' => 'wx-input',
                'name' => 'token',
                'label' => 'trans::webx-notifications::providers.telegram.token',
                'help' => 'trans::webx-notifications::screen.token-help',
                'props' => ['type' => 'password', 'autocomplete' => 'off'],
            ],
            [
                'id' => 'telegram_recipients',
                'type' => 'wx-notifications-chats',
                'name' => 'recipients',
                'label' => 'trans::webx-notifications::screen.chats',
                'help' => 'trans::webx-notifications::screen.chats-help',
            ],
        ];

        // Each node shown only on a channel of this kind; one with a condition of its own keeps it.
        return array_map(static fn (array $node): array => $node + ['visible' => $visible], $nodes);
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    private function found(array $chat): FoundRecipient
    {
        $name = trim(((string) ($chat['first_name'] ?? '')).' '.((string) ($chat['last_name'] ?? '')));
        $title = (string) ($chat['title'] ?? ($name !== '' ? $name : ($chat['username'] ?? $chat['id'])));
        $username = isset($chat['username']) ? '@'.$chat['username'] : null;

        return new FoundRecipient((string) $chat['id'], (string) ($chat['type'] ?? 'private'), $title, $username);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DeliveryFailed when Telegram cannot be reached at all — worth another attempt
     */
    private function call(Credentials $credentials, string $method, string $url, array $data = []): Response
    {
        try {
            $request = $this->http->timeout((int) $this->config->get('webx-notifications.telegram.timeout', 10))->acceptJson();

            return $method === 'get' ? $request->get($url, $data) : $request->asJson()->post($url, $data);
        } catch (Throwable $exception) {
            throw new DeliveryFailed($this->say('telegram.unreachable', ['error' => $credentials->mask($exception->getMessage())]));
        }
    }

    /** Telegram's answer as a failure (§6.1, the table of errors). */
    private function failure(Response $response, Credentials $credentials): DeliveryFailed
    {
        $status = $response->status();
        $description = $credentials->mask((string) ($response->json('description') ?? 'HTTP '.$status));

        if ($status === 401 || $status === 404) {
            return DeliveryFailed::permanent($this->say('telegram.token'));
        }

        if ($status === 429) {
            $wait = $response->json('parameters.retry_after');

            return new DeliveryFailed($this->say('telegram.wait', ['error' => $description]), false, is_numeric($wait) ? max(1, (int) $wait) : null);
        }

        $migrated = $response->json('parameters.migrate_to_chat_id');

        if ($migrated !== null) {
            return DeliveryFailed::permanent($this->say('telegram.migrated', ['chat' => (string) $migrated]));
        }

        if ($status >= 500 || $status === 0) {
            return new DeliveryFailed($this->say('telegram.refused', ['error' => $description]));
        }

        if ($status === 403 || str_contains(strtolower($description), 'chat not found')) {
            return DeliveryFailed::permanent($this->say('telegram.chat-gone', ['error' => $description]));
        }

        return DeliveryFailed::permanent($this->say('telegram.refused', ['error' => $description]));
    }

    private function url(string $token, string $method): string
    {
        return rtrim((string) $this->config->get('webx-notifications.telegram.api', 'https://api.telegram.org'), '/')."/bot{$token}/{$method}";
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function say(string $key, array $replace = []): string
    {
        $line = __('webx-notifications::errors.'.$key, $replace);

        return is_string($line) ? $line : $key;
    }
}
