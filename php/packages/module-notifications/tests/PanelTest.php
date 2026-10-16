<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\Notifications;

/**
 * The section's API (§10.5, §11): two permissions, every rule of a channel (§3.3), a copy, the
 * buttons of the form, and the log.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_section_is_in_the_panel_for_a_super_administrator_with_two_permissions(): void
    {
        $modules = $this->actingAs($this->admin(), 'cms')->getJson('/api/cms/manifest')->assertOk()->json('data.modules');
        $this->assertIsArray($modules);
        $section = array_values(array_filter($modules, static fn (mixed $module): bool => is_array($module) && ($module['id'] ?? null) === 'notifications'))[0] ?? null;

        $this->assertNotNull($section);
        $this->assertSame(['notifications.view', 'notifications.manage'], $section['permissions'] ?? null);
    }

    #[Test]
    public function reading_needs_view_writing_needs_manage(): void
    {
        $channel = $this->telegram();

        $this->actingAs($this->admin(false), 'cms')->getJson(self::API.'/channels')->assertForbidden();
        $this->actingAs($this->admin(false), 'cms')->getJson(self::API.'/deliveries')->assertForbidden();

        $reader = $this->admin(false, ['notifications.view']);
        $this->actingAs($reader, 'cms')->getJson(self::API.'/channels')->assertOk();
        $this->actingAs($reader, 'cms')->getJson(self::API.'/channels/'.$channel->id)->assertOk();
        $this->actingAs($reader, 'cms')->getJson(self::API.'/deliveries')->assertOk();
        $this->actingAs($reader, 'cms')->putJson(self::API.'/channels/'.$channel->id, ['values' => ['name' => 'x']])->assertForbidden();
        $this->actingAs($reader, 'cms')->postJson(self::API.'/channels/'.$channel->id.'/test')->assertForbidden();
        $this->actingAs($reader, 'cms')->postJson(self::API.'/find-recipients', ['channel' => $channel->id])->assertForbidden();

        $manager = $this->admin(false, ['notifications.manage']);
        $this->actingAs($manager, 'cms')->getJson(self::API.'/channels')->assertOk();
        $this->actingAs($manager, 'cms')->putJson(self::API.'/channels/'.$channel->id, ['values' => ['name' => 'Renamed']])->assertOk();
    }

    #[Test]
    public function a_telegram_channel_is_created_with_its_token_kept_encrypted_and_answered_only_as_set(): void
    {
        $response = $this->actingAs($this->admin(), 'cms')->postJson(self::API.'/channels', ['values' => [
            'name' => 'Orders',
            'provider' => 'telegram',
            'event' => 'shop.order',
            'token' => self::TOKEN,
            'recipients' => [['chat_id' => '100200300', 'label' => 'Managers']],
        ]])->assertCreated();

        $this->assertStringNotContainsString(self::TOKEN, (string) $response->getContent());
        $response->assertJsonPath('data.channel.credentials_set', ['token' => true]);
        $response->assertJsonPath('data.values.token', '');
        $response->assertJsonPath('data.values.template', ['en' => '<b>Order {{number}}</b> — {{total}}']);
        $response->assertJsonPath('data.channel.enabled', false);

        $channel = Channel::query()->sole();
        $raw = (string) $channel->getRawOriginal('credentials');
        $this->assertStringNotContainsString(self::TOKEN, $raw);
        $this->assertSame(self::TOKEN, $channel->secrets()->get('token'));
    }

    #[Test]
    public function an_email_channel_keeps_open_settings_open_and_the_password_secret(): void
    {
        $response = $this->actingAs($this->admin(), 'cms')->postJson(self::API.'/channels', ['values' => [
            'name' => 'Letters',
            'provider' => 'email',
            'mode' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => '465',
            'encryption' => 'ssl',
            'username' => 'robot',
            'password' => 'smtp-password-not-real',
            'from_address' => 'shop@example.com',
            'recipients' => [['email' => 'manager@example.com']],
            'subject' => ['en' => 'Hello'],
            'template' => ['en' => 'Body'],
            'enabled' => true,
        ]])->assertCreated();

        $this->assertStringNotContainsString('smtp-password-not-real', (string) $response->getContent());
        $response->assertJsonPath('data.values.host', 'smtp.example.com');
        $response->assertJsonPath('data.values.port', 465);
        $response->assertJsonPath('data.values.password', '');
        $response->assertJsonPath('data.channel.credentials_set', ['password' => true]);
        $response->assertJsonPath('data.channel.enabled', true);
    }

    #[Test]
    public function an_empty_secret_keeps_the_saved_one_and_forget_wipes_it_and_the_journal_says_only_that_it_changed(): void
    {
        $channel = $this->telegram();
        $admin = $this->admin();

        $this->actingAs($admin, 'cms')->putJson(self::API.'/channels/'.$channel->id, ['values' => ['token' => '', 'name' => 'Renamed']])->assertOk();
        $this->assertSame(self::TOKEN, $channel->refresh()->secrets()->get('token'));

        $this->actingAs($admin, 'cms')->putJson(self::API.'/channels/'.$channel->id, ['values' => ['token' => '000000002:ANOTHER-not-real-token']])->assertOk();
        $this->actingAs($admin, 'cms')->putJson(self::API.'/channels/'.$channel->id, ['values' => ['forget' => ['token'], 'enabled' => false]])->assertOk();

        $this->assertSame([], $channel->refresh()->credentialsSet());

        $journal = (string) json_encode(HistoryEntry::query()->where('subject_type', 'notifications')->pluck('changes')->all());
        $this->assertStringNotContainsString(self::TOKEN, $journal);
        $this->assertStringNotContainsString('ANOTHER', $journal);
        $this->assertStringContainsString('credentials.token', $journal);

        $history = $this->actingAs($admin, 'cms')->getJson('/api/cms/history?type=notifications&id='.$channel->id)->getContent();
        $this->assertStringNotContainsString(self::TOKEN, (string) $history);
    }

    #[Test]
    public function the_rules_of_a_channel_refuse_under_the_field(): void
    {
        $channel = $this->telegram();
        $admin = $this->admin();
        $put = fn (array $values) => $this->actingAs($admin, 'cms')->putJson(self::API.'/channels/'.$channel->id, ['values' => $values]);

        $put(['provider' => 'email'])->assertUnprocessable()->assertJsonValidationErrors('provider');
        $put(['template' => ['en' => '{{number}} {{nope}}']])->assertUnprocessable()->assertJsonValidationErrors('template');
        $put(['template' => ['en' => '<div>{{number}}</div>']])->assertUnprocessable()->assertJsonValidationErrors('template');
        $put(['event' => 'nobody.declares'])->assertUnprocessable()->assertJsonValidationErrors('event');
        $put(['recipients' => ['not a chat']])->assertUnprocessable()->assertJsonValidationErrors('recipients');
        $put(['recipients' => [['variable' => 'number']]])->assertUnprocessable()->assertJsonValidationErrors('recipients');
        $put(['recipients' => [], 'enabled' => true])->assertUnprocessable()->assertJsonValidationErrors('enabled');
        $put(['forget' => ['token']])->assertUnprocessable()->assertJsonValidationErrors('enabled');

        $this->actingAs($admin, 'cms')->postJson(self::API.'/channels', ['values' => ['name' => 'x', 'provider' => 'pigeon']])
            ->assertUnprocessable()->assertJsonValidationErrors('provider');
        $this->actingAs($admin, 'cms')->postJson(self::API.'/channels', ['values' => ['name' => 'x', 'provider' => 'email', 'recipients' => [['variable' => 'customer_email']]]])
            ->assertUnprocessable()->assertJsonValidationErrors('recipients');
    }

    #[Test]
    public function a_template_mustache_cannot_read_or_one_with_a_partial_is_refused_with_words_to_fix_it(): void
    {
        $channel = $this->telegram();
        $admin = $this->admin();
        $put = fn (string $template) => $this->actingAs($admin, 'cms')->putJson(self::API.'/channels/'.$channel->id, ['values' => ['template' => ['en' => $template]]]);

        $put('{{#items}}{{name}}')->assertUnprocessable()
            ->assertJsonPath('errors.template.0', 'The section {{#items}} is not closed: end it with {{/items}}.');
        $put('{{>footer}}')->assertUnprocessable()
            ->assertJsonPath('errors.template.0', 'Partials ({{>name}}) are not available in a message template.');
        $put('{{=<% %>=}}<% number %>')->assertUnprocessable()
            ->assertJsonPath('errors.template.0', 'Changing the delimiters ({{=<% %>=}}) is not available in a message template.');
        $put('{{/items}}')->assertUnprocessable()->assertJsonValidationErrors('template');
        $put('{{number')->assertUnprocessable()->assertJsonValidationErrors('template');
        $put('{{#items}}{{colour}}{{/items}}')->assertUnprocessable()->assertJsonValidationErrors('template');
        $put('{{#parcels}}{{name}}{{/parcels}}')->assertUnprocessable()->assertJsonValidationErrors('template');
        $put('{{items}}')->assertUnprocessable()
            ->assertJsonPath('errors.template.0', '{{items}} is a list: go over it with {{#items}}…{{/items}} and print the fields of each row.');
        $put('{{name}}')->assertUnprocessable()->assertJsonValidationErrors('template');
        // Telegram's markup is checked with the sections: a tag opened in a row closes in it.
        $put('{{#items}}<b>{{name}}{{/items}}</b>')->assertUnprocessable()->assertJsonValidationErrors('template');

        $put("<b>{{number}}</b>\n{{#items}}\n<i>{{name}}</i> × {{qty}} — {{price}}\n{{/items}}\n{{^items}}No items{{/items}}{{#note}}{{note}}{{/note}} {{{note}}} {{field.city}}")
            ->assertOk();
    }

    #[Test]
    public function an_addressee_of_the_event_is_a_recipient_of_a_channel_for_that_event(): void
    {
        $this->actingAs($this->admin(), 'cms')->postJson(self::API.'/channels', ['values' => [
            'name' => 'Receipt to the customer',
            'provider' => 'email',
            'event' => 'shop.order',
            'recipients' => [['variable' => 'customer_email']],
            'enabled' => true,
        ]])->assertCreated()->assertJsonPath('data.values.recipients', [['variable' => 'customer_email']]);
    }

    #[Test]
    public function a_channel_for_any_event_saves_an_unknown_variable_with_a_warning(): void
    {
        $this->actingAs($this->admin(), 'cms')->postJson(self::API.'/channels', ['values' => [
            'name' => 'Any', 'provider' => 'telegram', 'template' => 'Hi {{nmuber}}',
        ]])->assertCreated()
            ->assertJsonPath('data.values.template', ['en' => 'Hi {{nmuber}}'])
            ->assertJsonCount(1, 'data.warnings');
    }

    #[Test]
    public function a_copy_is_switched_off_says_where_it_came_from_and_carries_the_secrets_without_showing_them(): void
    {
        $channel = $this->telegram();

        $response = $this->actingAs($this->admin(), 'cms')->postJson(self::API.'/channels/'.$channel->id.'/copy')->assertCreated();

        $this->assertStringNotContainsString(self::TOKEN, (string) $response->getContent());
        $response->assertJsonPath('data.channel.name', 'Orders in Telegram (copy)');
        $response->assertJsonPath('data.channel.enabled', false);
        $response->assertJsonPath('data.channel.copied_from', $channel->id);
        $response->assertJsonPath('data.channel.credentials_set', ['token' => true]);

        $copy = Channel::query()->findOrFail($response->json('data.channel.id'));
        $this->assertSame(self::TOKEN, $copy->secrets()->get('token'));
        $this->assertSame($channel->getTranslations('template'), $copy->getTranslations('template'));
    }

    #[Test]
    public function the_bin_takes_a_channel_and_gives_it_back(): void
    {
        $channel = $this->telegram();
        $admin = $this->admin();

        $this->actingAs($admin, 'cms')->deleteJson(self::API.'/channels/'.$channel->id)->assertNoContent();
        $this->actingAs($admin, 'cms')->getJson(self::API.'/channels?trashed=1')->assertJsonPath('data.0.id', $channel->id);
        $this->actingAs($admin, 'cms')->postJson(self::API.'/channels/'.$channel->id.'/restore')->assertOk();
        $this->actingAs($admin, 'cms')->deleteJson(self::API.'/channels/'.$channel->id)->assertNoContent();
        $this->actingAs($admin, 'cms')->deleteJson(self::API.'/channels/'.$channel->id.'/purge')->assertNoContent();

        $this->assertSame(0, Channel::query()->withTrashed()->count());
    }

    #[Test]
    public function a_test_from_an_unsaved_form_uses_the_typed_token_once_and_tests_are_limited(): void
    {
        config()->set('webx-notifications.tests_per_minute', 2);
        Http::fake(['telegram.test/*' => Http::response(['ok' => true])]);
        $admin = $this->admin();
        $values = ['name' => 'Draft', 'provider' => 'telegram', 'event' => 'shop.order', 'token' => self::TOKEN, 'recipients' => [['chat_id' => '7']], 'template' => ['en' => '{{number}}']];

        $this->actingAs($admin, 'cms')->postJson(self::API.'/test', ['values' => $values])
            ->assertOk()->assertJsonPath('data.results.0.status', 'sent');
        $this->actingAs($admin, 'cms')->postJson(self::API.'/test', ['values' => $values])->assertOk();
        $this->actingAs($admin, 'cms')->postJson(self::API.'/test', ['values' => $values])->assertStatus(429);

        $this->assertSame(0, Channel::query()->count());
        $this->assertSame([true, true], Delivery::query()->pluck('is_test')->all());
        Http::assertSent(static fn ($request): bool => $request['text'] === '1024');
    }

    #[Test]
    public function preview_renders_the_examples_and_sends_nothing(): void
    {
        Http::fake();
        $channel = $this->telegram();

        $this->actingAs($this->admin(), 'cms')->postJson(self::API.'/channels/'.$channel->id.'/preview')
            ->assertOk()
            ->assertJsonPath('data.body', '<b>Order 1024</b>: Leave at the door');

        Http::assertNothingSent();
        $this->assertSame(0, Delivery::query()->count());
    }

    #[Test]
    public function check_names_the_bot_and_the_saved_channel_remembers_it(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => true, 'result' => ['username' => 'orders_test_bot']])]);
        $channel = $this->telegram();
        $admin = $this->admin();

        $this->actingAs($admin, 'cms')->postJson(self::API.'/check', ['channel' => $channel->id])
            ->assertOk()->assertJsonPath('data.ok', true)->assertJsonPath('data.details.bot', '@orders_test_bot');

        $this->actingAs($admin, 'cms')->getJson(self::API.'/channels/'.$channel->id)->assertJsonPath('data.channel.bot', '@orders_test_bot');
    }

    #[Test]
    public function find_chats_works_with_the_saved_token_or_a_typed_one(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => true, 'result' => [['update_id' => 1, 'message' => ['chat' => ['id' => 5, 'type' => 'private', 'first_name' => 'Alex']]]]])]);
        $channel = $this->telegram();
        $admin = $this->admin();

        $this->actingAs($admin, 'cms')->postJson(self::API.'/find-recipients', ['channel' => $channel->id])
            ->assertOk()->assertJsonPath('data.recipients.0.id', '5');
        $this->actingAs($admin, 'cms')->postJson(self::API.'/find-recipients', ['provider' => 'telegram', 'token' => self::TOKEN])
            ->assertOk()->assertJsonPath('data.recipients.0.title', 'Alex');
        $this->actingAs($admin, 'cms')->postJson(self::API.'/find-recipients', ['provider' => 'email'])->assertUnprocessable();

        Http::assertSent(static fn ($request): bool => str_contains($request->url(), 'bot'.self::TOKEN.'/getUpdates'));
    }

    #[Test]
    public function the_log_is_filtered_and_a_failed_row_is_sent_again(): void
    {
        Bus::fake();
        $channel = $this->telegram();
        $notifications = $this->app->make(Notifications::class);
        $notifications->send('shop.order', [$channel->id], ['number' => '1']);
        $notifications->send('shop.order', [$channel->id], ['number' => '2']);
        Delivery::query()->orderBy('id')->first()?->forceFill(['status' => Delivery::FAILED, 'error' => 'Bad Gateway'])->save();
        $admin = $this->admin();

        $failed = $this->actingAs($admin, 'cms')->getJson(self::API.'/deliveries?status=failed')->assertOk();
        $failed->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_url', 'https://example.test/cms/orders/1')
            ->assertJsonPath('data.0.retryable', true)
            ->assertJsonPath('total', 1);

        $this->actingAs($admin, 'cms')->postJson(self::API.'/deliveries/'.$failed->json('data.0.id').'/retry')
            ->assertOk()->assertJsonPath('data.status', 'queued');

        $sent = Delivery::query()->orderByDesc('id')->firstOrFail();
        $sent->forceFill(['status' => Delivery::SENT])->save();
        $this->actingAs($admin, 'cms')->postJson(self::API.'/deliveries/'.$sent->id.'/retry')->assertUnprocessable();
    }

    #[Test]
    public function meta_lists_providers_and_events_and_the_form_gets_each_providers_fields(): void
    {
        $this->actingAs($this->admin(), 'cms')->getJson(self::API.'/meta')
            ->assertOk()
            ->assertJsonPath('data.providers.0.key', 'telegram')
            ->assertJsonPath('data.providers.0.find_recipients', true)
            ->assertJsonPath('data.providers.1.subject', true)
            ->assertJsonPath('data.events.0.key', 'shop.order');

        $ids = [];
        $tree = $this->app->make(ScreenRegistry::class)->tree(Channel::SCREEN);
        array_walk_recursive($tree, static function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'id') {
                $ids[] = $value;
            }
        });

        foreach (['token', 'telegram_recipients', 'mode', 'host', 'password', 'email_recipients', 'template', 'subject'] as $id) {
            $this->assertContains($id, $ids);
        }
    }

    #[Test]
    public function no_answer_of_the_section_carries_a_secret(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => false, 'description' => 'Unauthorized: bot'.self::TOKEN], 401)]);
        Bus::fake();
        $channel = $this->telegram();
        $email = $this->email(['options' => ['mode' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none'], 'credentials' => ['password' => 'smtp-password-not-real']]);
        $admin = $this->admin();
        $this->app->make(Notifications::class)->send('shop.order', [$channel->id], ['number' => '1']);

        $answers = [
            $this->actingAs($admin, 'cms')->getJson(self::API.'/channels'),
            $this->actingAs($admin, 'cms')->getJson(self::API.'/channels/'.$channel->id),
            $this->actingAs($admin, 'cms')->getJson(self::API.'/channels/'.$email->id),
            $this->actingAs($admin, 'cms')->getJson(self::API.'/meta'),
            $this->actingAs($admin, 'cms')->getJson(self::API.'/deliveries'),
            $this->actingAs($admin, 'cms')->putJson(self::API.'/channels/'.$channel->id, ['values' => ['name' => 'Renamed']]),
            $this->actingAs($admin, 'cms')->putJson(self::API.'/channels/'.$channel->id, ['values' => ['template' => ['en' => '<div>']]]),
            $this->actingAs($admin, 'cms')->postJson(self::API.'/channels/'.$channel->id.'/copy'),
            $this->actingAs($admin, 'cms')->postJson(self::API.'/channels/'.$channel->id.'/test'),
            $this->actingAs($admin, 'cms')->postJson(self::API.'/channels/'.$email->id.'/test'),
            $this->actingAs($admin, 'cms')->postJson(self::API.'/check', ['channel' => $channel->id]),
            $this->actingAs($admin, 'cms')->postJson(self::API.'/check', ['channel' => $email->id]),
            $this->actingAs($admin, 'cms')->postJson(self::API.'/find-recipients', ['channel' => $channel->id]),
            $this->actingAs($admin, 'cms')->getJson('/api/cms/history?type=notifications&id='.$channel->id),
        ];

        foreach ($answers as $index => $answer) {
            $this->assertLessThan(500, $answer->status(), "Answer {$index}: ".$answer->getContent());
            $this->assertStringNotContainsString(self::TOKEN, (string) $answer->getContent(), "Answer {$index}");
            $this->assertStringNotContainsString('smtp-password-not-real', (string) $answer->getContent(), "Answer {$index}");
        }

        $this->assertStringNotContainsString(self::TOKEN, (string) json_encode(Delivery::query()->pluck('error')->all()));
    }
}
