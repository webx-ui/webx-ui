<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Support\Field;
use WebxUi\Notifications\Sending\Credentials;
use WebxUi\Notifications\Sending\DeliveryFailed;
use WebxUi\Notifications\Sending\Message;
use WebxUi\Notifications\Sending\Recipient;
use WebxUi\Notifications\Telegram\TelegramProvider;

/**
 * The Telegram provider against a fake Bot API (§6.1, §6.3): what it sends, how it reads every
 * answer in the table of errors, and that the token never comes out in words.
 */
final class TelegramTest extends TestCase
{
    #[Test]
    public function the_settings_of_a_provider_are_the_shared_field_of_the_panel(): void
    {
        // Decision 21 of the visitors' spec: one Field in module-admin, no copy left here.
        self::assertFalse(class_exists('WebxUi\Notifications\Sending\Field'));
        self::assertContainsOnlyInstancesOf(Field::class, $this->provider()->fields());
        self::assertTrue(array_any($this->provider()->fields(), static fn (Field $field): bool => $field->secret));
    }

    #[Test]
    public function a_message_goes_as_html_without_a_link_preview(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->provider()->send(new Message(null, '<b>Hi</b>', 'Hi'), $this->credentials(), [], new Recipient('-100200300'));

        Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://telegram.test/bot'.self::TOKEN.'/sendMessage'
            && $request['chat_id'] === '-100200300'
            && $request['text'] === '<b>Hi</b>'
            && $request['parse_mode'] === 'HTML'
            && $request['link_preview_options'] === ['is_disabled' => true]);
    }

    #[Test]
    public function a_cut_message_goes_as_plain_text(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => true])]);

        $this->provider()->send(new Message(null, '&lt;…', '<…', true), $this->credentials(), [], new Recipient('1'));

        Http::assertSent(static fn (Request $request): bool => $request['text'] === '<…' && ! isset($request['parse_mode']));
    }

    #[Test]
    public function every_answer_in_the_table_is_read_as_permanent_or_worth_another_attempt(): void
    {
        $cases = [
            [401, ['ok' => false, 'description' => 'Unauthorized'], true, null, 'did not accept the bot token'],
            [400, ['ok' => false, 'description' => 'Bad Request: chat not found'], true, null, 'not in this chat'],
            [403, ['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], true, null, 'not in this chat'],
            [400, ['ok' => false, 'description' => 'Bad Request: group chat was upgraded to a supergroup chat', 'parameters' => ['migrate_to_chat_id' => -1009998887776]], true, null, '-1009998887776'],
            [400, ['ok' => false, 'description' => "Bad Request: can't parse entities"], true, null, "can't parse entities"],
            [429, ['ok' => false, 'description' => 'Too Many Requests: retry after 17', 'parameters' => ['retry_after' => 17]], false, 17, 'asked to wait'],
            [502, ['ok' => false, 'description' => 'Bad Gateway'], false, null, 'Bad Gateway'],
        ];

        $sequence = Http::sequence();

        foreach ($cases as [$status, $body]) {
            $sequence->push($body, $status);
        }

        Http::fake(['telegram.test/*' => $sequence]);

        foreach ($cases as [$status, $body, $permanent, $wait, $words]) {
            try {
                $this->provider()->send(new Message(null, 'x', 'x'), $this->credentials(), [], new Recipient('1'));
                $this->fail("{$status} was taken as sent.");
            } catch (DeliveryFailed $failed) {
                $this->assertSame($permanent, $failed->permanent, (string) $status);
                $this->assertSame($wait, $failed->retryAfter, (string) $status);
                $this->assertStringContainsString($words, $failed->getMessage());
            }
        }
    }

    #[Test]
    public function the_token_is_never_in_the_words_of_a_failure(): void
    {
        Http::fake(static fn () => throw new ConnectionException('cURL error 6 for https://telegram.test/bot'.self::TOKEN.'/sendMessage'));

        try {
            $this->provider()->send(new Message(null, 'x', 'x'), $this->credentials(), [], new Recipient('1'));
            $this->fail('An unreachable Telegram was taken as sent.');
        } catch (DeliveryFailed $failed) {
            $this->assertFalse($failed->permanent);
            $this->assertStringNotContainsString(self::TOKEN, $failed->getMessage());
            $this->assertStringContainsString('bot***/sendMessage', $failed->getMessage());
        }
    }

    #[Test]
    public function check_names_the_bot(): void
    {
        Http::fake(['telegram.test/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true, 'username' => 'orders_test_bot']])]);

        $result = $this->provider()->check($this->credentials(), []);

        $this->assertTrue($result->ok);
        $this->assertSame('@orders_test_bot', $result->details['bot']);
        $this->assertFalse($this->provider()->check(new Credentials, [])->ok);
    }

    #[Test]
    public function find_chats_reads_updates_without_an_offset_and_lists_each_chat_once(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => true, 'result' => [
            ['update_id' => 1, 'message' => ['chat' => ['id' => 100200300, 'type' => 'private', 'first_name' => 'Alex', 'last_name' => 'Doe', 'username' => 'alex_test']]],
            ['update_id' => 2, 'message' => ['chat' => ['id' => 100200300, 'type' => 'private', 'first_name' => 'Alex']]],
            ['update_id' => 3, 'my_chat_member' => ['chat' => ['id' => -100400500600, 'type' => 'supergroup', 'title' => 'Managers']]],
            ['update_id' => 4, 'channel_post' => ['chat' => ['id' => -100700800900, 'type' => 'channel', 'title' => 'News', 'username' => 'news_test']]],
        ]])]);

        $found = array_map(static fn ($one): array => $one->toArray(), $this->provider()->find($this->credentials()));

        $this->assertSame([
            ['id' => '100200300', 'type' => 'private', 'title' => 'Alex', 'username' => null],
            ['id' => '-100400500600', 'type' => 'supergroup', 'title' => 'Managers', 'username' => null],
            ['id' => '-100700800900', 'type' => 'channel', 'title' => 'News', 'username' => '@news_test'],
        ], $found);

        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/getUpdates') && ! str_contains($request->url(), 'offset'));
    }

    #[Test]
    public function a_bot_with_a_webhook_is_said_in_words_and_the_webhook_is_left_alone(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => false, 'error_code' => 409, 'description' => "Conflict: can't use getUpdates method while webhook is active"], 409)]);

        try {
            $this->provider()->find($this->credentials());
            $this->fail('A webhook was not noticed.');
        } catch (DeliveryFailed $failed) {
            $this->assertStringContainsString('webhook', $failed->getMessage());
        }

        Http::assertSentCount(1);
        Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), 'deleteWebhook'));
    }

    #[Test]
    public function recipients_are_chat_ids_or_usernames_and_an_addressee_comes_from_the_event(): void
    {
        $provider = $this->provider();

        $this->assertSame(
            [['chat_id' => '-100200300', 'label' => 'Managers'], ['chat_id' => '@news_test'], ['variable' => 'telegram_id']],
            $provider->recipients([['chat_id' => '-100200300', 'label' => 'Managers'], '@news_test', ['chat_id' => '-100200300'], ['variable' => 'telegram_id']]),
        );

        $resolved = $provider->resolve([['chat_id' => '1'], ['variable' => 'telegram_id'], ['variable' => 'other']], ['telegram_id' => '555', 'other' => 'not a chat']);

        $this->assertSame(['1', '555', '{{other}}'], array_map(static fn (Recipient $one): string => $one->key, $resolved));
        $this->assertSame('no_address', $resolved[2]->skip);
    }

    private function provider(): TelegramProvider
    {
        return $this->app->make(TelegramProvider::class);
    }

    private function credentials(): Credentials
    {
        return new Credentials(['token' => self::TOKEN]);
    }
}
