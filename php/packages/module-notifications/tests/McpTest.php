<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use ArrayObject;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Notifications\Jobs\Deliver;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\Notifications;

/**
 * The section by its other doors (§12): the same values and refusals as the panel, secrets that
 * go in and never come out, and a dry run that writes nothing and sends nothing.
 */
final class McpTest extends TestCase
{
    private const TOOLS = [
        'notifications_channels_list' => ['notifications:read', ['notifications.view', 'notifications.manage'], false],
        'notifications_channel_get' => ['notifications:read', ['notifications.view', 'notifications.manage'], false],
        'notifications_channel_create' => ['notifications:write', ['notifications.manage'], true],
        'notifications_channel_update' => ['notifications:write', ['notifications.manage'], true],
        'notifications_channel_delete' => ['notifications:write', ['notifications.manage'], true],
        'notifications_channel_copy' => ['notifications:write', ['notifications.manage'], true],
        'notifications_preview' => ['notifications:read', ['notifications.view', 'notifications.manage'], false],
        'notifications_test' => ['notifications:write', ['notifications.manage'], true],
        'notifications_find_chats' => ['notifications:write', ['notifications.manage'], true],
        'notifications_deliveries' => ['notifications:read', ['notifications.view', 'notifications.manage'], false],
        'notifications_retry' => ['notifications:write', ['notifications.manage'], true],
    ];

    #[Test]
    public function the_tools_are_the_sections_by_name_scope_and_permission(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $tools = [];

        foreach ($registry->toolsOf('notifications') as $tool) {
            $tools[$tool->fullName()] = [$tool->scope(), $tool->permissions(), $tool->tool->mutating];
        }

        $this->assertSame(self::TOOLS, $tools);

        foreach (self::TOOLS as $name => [, , $mutating]) {
            if ($mutating) {
                // The agent is told what a write is before it decides to make one.
                $this->assertStringStartsWith('A channel sends messages to real people outside the site.', $registry->tool($name)->tool->description);
                $this->assertArrayHasKey('dry_run', $registry->tool($name)->tool->inputSchema['properties']);
            }
        }

        $uris = array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources());
        $this->assertContains('notifications://providers', $uris);
        $this->assertContains('notifications://events', $uris);
        $this->assertContains('notifications://channels', $uris);
    }

    #[Test]
    public function the_tools_are_listed_by_the_command(): void
    {
        $this->artisan('webx:mcp-tools', ['--module' => 'notifications'])
            ->expectsOutputToContain('notifications_channel_create')
            ->expectsOutputToContain('notifications_find_chats')
            ->assertSuccessful();
    }

    #[Test]
    public function reading_needs_view_and_writing_needs_manage(): void
    {
        $channel = $this->telegram();
        $editor = $this->admin(super: false, permissions: ['pages.manage']);

        $this->agent('notifications_channels_list', [], $editor)->assertHasErrors(['notifications.view']);

        $reader = $this->admin(super: false, permissions: ['notifications.view']);
        $this->agent('notifications_channels_list', [], $reader)->assertOk();
        $this->agent('notifications_channel_get', ['channel' => $channel->id], $reader)->assertOk();
        $this->agent('notifications_deliveries', [], $reader)->assertOk();
        $this->agent('notifications_preview', ['channel' => $channel->id], $reader)->assertOk();
        $this->agent('notifications_channel_update', ['channel' => $channel->id, 'name' => 'x'], $reader)->assertHasErrors(['notifications.manage']);
        $this->agent('notifications_test', ['channel' => $channel->id, 'dry_run' => true], $reader)->assertHasErrors(['notifications.manage']);

        $manager = $this->admin(super: false, permissions: ['notifications.manage']);
        $this->agent('notifications_channels_list', [], $manager)->assertOk();
        $this->agent('notifications_channel_update', ['channel' => $channel->id, 'name' => 'Renamed'], $manager)->assertOk();
    }

    #[Test]
    public function create_get_update_copy_delete_go_through_the_form(): void
    {
        $created = $this->content($this->agent('notifications_channel_create', [
            'name' => 'Orders',
            'provider' => 'telegram',
            'event' => 'shop.order',
            'credentials' => ['token' => self::TOKEN],
            'recipients' => [['chat_id' => '-100200300', 'label' => 'Managers']],
        ]));

        $id = $created['channel']['id'];
        $this->assertIsInt($id);
        // Off unless asked for, the event's sample as the template, the token only as "set".
        $this->assertFalse($created['channel']['enabled']);
        $this->assertSame(['token' => true], $created['channel']['credentials_set']);
        $this->assertArrayNotHasKey('token', $created['values']);
        $this->assertSame(['en' => '<b>Order {{number}}</b> — {{total}}'], $created['values']['template']);
        $this->assertSame(self::TOKEN, Channel::query()->findOrFail($id)->secrets()->get('token'));

        $got = $this->content($this->agent('notifications_channel_get', ['channel' => $id]));
        $this->assertSame([['chat_id' => '-100200300', 'label' => 'Managers']], $got['values']['recipients']);

        $updated = $this->content($this->agent('notifications_channel_update', [
            'channel' => $id,
            'enabled' => true,
            'template' => ['en' => '{{#items}}{{name}} × {{qty}}{{/items}}', 'ru' => 'Заказ {{number}}'],
        ]));
        $this->assertTrue($updated['channel']['enabled']);
        $this->assertSame('Orders', $updated['values']['name']);
        $this->assertSame(['token' => true], $updated['channel']['credentials_set']);

        $copy = $this->content($this->agent('notifications_channel_copy', ['channel' => $id, 'name' => 'Orders, second chat']));
        $this->assertFalse($copy['channel']['enabled']);
        $this->assertSame($id, $copy['channel']['copied_from']);
        $this->assertSame(['token' => true], $copy['channel']['credentials_set']);

        $this->agent('notifications_channel_update', ['channel' => $id, 'provider' => 'email'])->assertHasErrors(['`provider` cannot be changed']);

        $this->agent('notifications_channel_delete', ['channel' => $id])->assertOk();
        $this->assertTrue(Channel::withTrashed()->findOrFail($id)->trashed());
        $this->agent('notifications_channel_update', ['channel' => $id, 'name' => 'Again'])->assertHasErrors(['is in the bin']);

        $listed = $this->content($this->agent('notifications_channels_list', ['trashed' => true]));
        $this->assertSame([$id], array_column($listed['channels'], 'id'));
    }

    #[Test]
    public function a_secret_is_written_wiped_and_never_answered(): void
    {
        $channel = $this->email(['options' => ['mode' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587], 'credentials' => ['password' => 'secret-password-1']]);

        $this->agent('notifications_channel_update', ['channel' => $channel->id, 'credentials' => ['password' => 'secret-password-2']])->assertOk();
        $this->assertSame('secret-password-2', $channel->refresh()->secrets()->get('password'));

        $wiped = $this->content($this->agent('notifications_channel_update', ['channel' => $channel->id, 'forget' => ['password']]));
        $this->assertSame([], $wiped['channel']['credentials_set']);
        $this->assertNull($channel->refresh()->secrets()->get('password'));
    }

    #[Test]
    public function no_answer_carries_a_secret(): void
    {
        Bus::fake();
        // What Telegram answers changes halfway through: a holder the fake reads at each call.
        $telegram = new ArrayObject(['refuses' => false]);
        Http::fake(static function () use ($telegram) {
            return $telegram['refuses']
                ? Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)
                : Http::response(['ok' => true, 'result' => []]);
        });
        $channel = $this->telegram();
        $failed = $this->failedDelivery($channel);

        $answers = [
            $this->agent('notifications_channels_list'),
            $this->agent('notifications_channel_get', ['channel' => $channel->id]),
            $this->agent('notifications_channel_create', ['name' => 'X', 'provider' => 'telegram', 'credentials' => ['token' => self::TOKEN], 'dry_run' => true]),
            $this->agent('notifications_channel_create', ['name' => 'X', 'provider' => 'telegram', 'credentials' => ['token' => self::TOKEN], 'template' => '{{#a}}', 'dry_run' => true]),
            $this->agent('notifications_channel_update', ['channel' => $channel->id, 'name' => 'Y', 'dry_run' => true]),
            $this->agent('notifications_channel_copy', ['channel' => $channel->id, 'dry_run' => true]),
            $this->agent('notifications_preview', ['channel' => $channel->id]),
            $this->agent('notifications_test', ['channel' => $channel->id, 'dry_run' => true]),
            $this->agent('notifications_test', ['channel' => $channel->id]),
            $this->agent('notifications_find_chats', ['channel' => $channel->id]),
            $this->agent('notifications_deliveries'),
            $this->agent('notifications_retry', ['delivery' => $failed->id, 'dry_run' => true]),
        ];

        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if (str_starts_with($resource->uri, 'notifications://')) {
                $this->assertStringNotContainsString(self::TOKEN, (string) json_encode(($resource->handler)()), $resource->uri);
            }
        }

        foreach ($answers as $index => $answer) {
            $answer->assertDontSee(self::TOKEN);
        }

        // A token Telegram refuses comes back in its words, the token in them masked.
        $telegram['refuses'] = true;
        $refused = $this->agent('notifications_find_chats', ['token' => self::TOKEN]);
        $refused->assertHasErrors(['Telegram did not accept the bot token']);
        $refused->assertDontSee(self::TOKEN);
    }

    #[Test]
    public function a_dry_run_writes_nothing_and_sends_nothing(): void
    {
        Bus::fake();
        Http::fake();
        $channel = $this->telegram();
        $failed = $this->failedDelivery($channel);

        $created = $this->content($this->agent('notifications_channel_create', [
            'name' => 'Orders by mail',
            'provider' => 'email',
            'event' => 'shop.order',
            'recipients' => [['email' => 'manager@example.com'], ['variable' => 'customer_email']],
            'dry_run' => true,
        ]));
        $this->assertTrue($created['dry_run']);
        $this->assertNull($created['channel']['id']);
        $this->assertSame(['en' => 'Order {{number}}'], $created['values']['subject']);

        $updated = $this->content($this->agent('notifications_channel_update', ['channel' => $channel->id, 'name' => 'Renamed', 'enabled' => false, 'dry_run' => true]));
        $this->assertSame(['name', 'enabled'], $updated['fields']);
        $this->assertSame('Renamed', $updated['values']['name']);

        $this->content($this->agent('notifications_channel_copy', ['channel' => $channel->id, 'dry_run' => true]));
        $this->content($this->agent('notifications_channel_delete', ['channel' => $channel->id, 'dry_run' => true]));

        $test = $this->content($this->agent('notifications_test', ['channel' => $channel->id, 'dry_run' => true]));
        $this->assertSame('100200300', $test['would_send_to'][0]['recipient']);

        $this->content($this->agent('notifications_find_chats', ['channel' => $channel->id, 'dry_run' => true]));

        $retry = $this->content($this->agent('notifications_retry', ['delivery' => $failed->id, 'dry_run' => true]));
        $this->assertSame('failed', $retry['would_retry']['status']);

        $this->assertSame(1, Channel::withTrashed()->count());
        $channel->refresh();
        $this->assertSame('Orders in Telegram', $channel->name);
        $this->assertTrue($channel->enabled);
        $this->assertFalse($channel->trashed());
        $this->assertSame(1, Delivery::query()->count());
        $this->assertSame(Delivery::FAILED, $failed->refresh()->status);
        Http::assertNothingSent();
        Bus::assertNothingDispatched();
    }

    #[Test]
    public function a_template_mustache_cannot_read_is_refused_with_what_to_fix(): void
    {
        $base = ['name' => 'Orders', 'provider' => 'telegram', 'event' => 'shop.order'];

        $this->agent('notifications_channel_create', $base + ['template' => "{{#items}}\n- {{name}}"])
            ->assertHasErrors(['Not accepted — template: The section {{#items}} is not closed: end it with {{/items}}.']);
        $this->agent('notifications_channel_create', $base + ['template' => 'Hi {{>footer}}'])
            ->assertHasErrors(['Partials ({{>name}}) are not available']);
        $this->agent('notifications_channel_create', $base + ['template' => '{{=<% %>=}}', 'dry_run' => true])
            ->assertHasErrors(['Changing the delimiters']);
        $this->agent('notifications_channel_create', $base + ['template' => 'Order {{numbr}}'])
            ->assertHasErrors(['The template uses {{numbr}}, which the event "shop.order" does not have.']);
        $this->agent('notifications_channel_create', $base + ['template' => 'All: {{items}}'])
            ->assertHasErrors(['{{items}} is a list']);
        $this->agent('notifications_preview', ['provider' => 'telegram', 'template' => '{{#items}}<b>{{name}}{{/items}}</b>'])
            ->assertHasErrors(['template:']);

        $this->assertSame(0, Channel::withTrashed()->count());
    }

    #[Test]
    public function unknown_providers_events_and_ids_are_named_with_what_there_is(): void
    {
        $this->agent('notifications_channel_create', ['name' => 'X', 'provider' => 'slack'])
            ->assertHasErrors(['Provider [slack] is not registered. Providers: telegram, email — notifications://providers.']);
        $this->agent('notifications_channel_create', ['name' => 'X', 'provider' => 'email', 'event' => 'shop.refund'])
            ->assertHasErrors(['Event [shop.refund] is not declared. Events: shop.order — notifications://events.']);
        $this->agent('notifications_channel_get', ['channel' => 404])
            ->assertHasErrors(['No channel has the id [404]. notifications_channels_list has them all.']);
        $this->agent('notifications_channel_create', ['name' => 'X', 'provider' => 'telegram', 'credentials' => ['token' => self::TOKEN], 'recipients' => [['chat_id' => '1']], 'template' => 'Hi', 'enabled' => true, 'colour' => 'red'])
            ->assertHasErrors(['colour']);
        $this->agent('notifications_channel_create', ['name' => 'X', 'provider' => 'telegram', 'recipients' => [['chat_id' => '1']], 'template' => 'Hi', 'enabled' => true])
            ->assertHasErrors(['enabled: The channel cannot be switched on: the bot token is not set.']);
    }

    #[Test]
    public function preview_renders_the_examples_of_the_event_and_sends_nothing(): void
    {
        Http::fake();

        $preview = $this->content($this->agent('notifications_preview', [
            'provider' => 'email',
            'event' => 'shop.order',
            'subject' => 'Order {{number}}',
            'template' => "Order {{number}}\n{{#items}}- {{name}} × {{qty}}\n{{/items}}{{^note}}No note{{/note}}",
        ]));

        $this->assertSame('Order 1024', $preview['subject']);
        $this->assertSame("Order 1024\n- Tea × 2\n", $preview['body']);
        $this->assertSame([], $preview['unknown_variables']);

        $saved = $this->telegram(['template' => ['en' => '<b>{{number}}</b>', 'ru' => '<b>Заказ {{number}}</b>']]);
        $this->assertSame('<b>Заказ 1024</b>', $this->content($this->agent('notifications_preview', ['channel' => $saved->id, 'locale' => 'ru']))['body']);
        // The given fields over the saved channel, the channel itself untouched.
        $this->assertSame('#1024', $this->content($this->agent('notifications_preview', ['channel' => $saved->id, 'template' => '#{{number}}']))['body']);
        $this->assertSame('<b>{{number}}</b>', $saved->refresh()->getTranslation('template', 'en'));

        Http::assertNothingSent();
    }

    #[Test]
    public function a_test_goes_now_to_the_fixed_recipients_and_is_logged_as_a_test(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => true, 'result' => []])]);
        $channel = $this->telegram(['enabled' => false]);

        $results = $this->content($this->agent('notifications_test', ['channel' => $channel->id]))['results'];

        $this->assertSame('sent', $results[0]['status']);
        Http::assertSent(static fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage') && $request['text'] === '<b>Order 1024</b>: Leave at the door');
        $this->assertTrue(Delivery::query()->sole()->is_test);

        $this->agent('notifications_test', ['channel' => $channel->id, 'recipient' => '999'])->assertHasErrors(['has no recipient [999]']);
    }

    #[Test]
    public function find_chats_lists_what_the_bot_was_written_to_and_says_why_not(): void
    {
        $telegram = new ArrayObject(['webhook' => false]);
        $updates = ['ok' => true, 'result' => [
            ['update_id' => 1, 'message' => ['chat' => ['id' => 100200300, 'type' => 'private', 'first_name' => 'Alex', 'username' => 'alex_test']]],
            ['update_id' => 2, 'my_chat_member' => ['chat' => ['id' => -100400500, 'type' => 'supergroup', 'title' => 'Managers']]],
        ]];
        Http::fake(static function () use ($telegram, $updates) {
            return $telegram['webhook'] ? Http::response(['ok' => false, 'error_code' => 409, 'description' => 'Conflict'], 409) : Http::response($updates);
        });

        $found = $this->content($this->agent('notifications_find_chats', ['token' => self::TOKEN]));

        $this->assertSame(['100200300', '-100400500'], array_column($found['chats'], 'id'));
        $this->assertSame(0, Channel::withTrashed()->count());

        $this->agent('notifications_find_chats', ['channel' => $this->email()->id])->assertHasErrors(['cannot look for recipients']);
        $this->agent('notifications_find_chats', [])->assertHasErrors(['Give `channel`']);

        $telegram['webhook'] = true;
        $this->agent('notifications_find_chats', ['token' => self::TOKEN])->assertHasErrors(['webhook']);
    }

    #[Test]
    public function the_log_is_read_and_a_failed_row_is_sent_again(): void
    {
        Bus::fake();
        $channel = $this->telegram();
        $failed = $this->failedDelivery($channel);
        $this->app->make(Notifications::class)->send('shop.order', [$channel->id], ['number' => '7']);

        $log = $this->content($this->agent('notifications_deliveries', ['status' => 'failed']));
        $this->assertSame([$failed->id], array_column($log['deliveries'], 'id'));
        $this->assertSame('https://example.test/cms/orders/1024', $log['deliveries'][0]['subject_url']);
        $this->assertFalse($log['more']);
        $this->assertCount(1, $this->content($this->agent('notifications_deliveries', ['limit' => 1]))['deliveries']);

        $retried = $this->content($this->agent('notifications_retry', ['delivery' => $failed->id]));
        $this->assertSame('queued', $retried['status']);
        Bus::assertDispatched(Deliver::class);

        $sent = Delivery::query()->where('status', Delivery::QUEUED)->where('id', '!=', $failed->id)->sole();
        $sent->forceFill(['status' => Delivery::SENT])->save();
        $this->agent('notifications_retry', ['delivery' => $sent->id])->assertHasErrors(['only failed or stuck deliveries']);
        $this->agent('notifications_deliveries', ['since' => 'yesterday-ish'])->assertHasErrors(['`since` is not a date']);
    }

    #[Test]
    public function the_resources_say_what_providers_events_and_channels_there_are(): void
    {
        $this->telegram();

        $providers = $this->resource('notifications://providers')['providers'];
        $telegram = array_values(array_filter($providers, static fn (array $one): bool => $one['key'] === 'telegram'))[0];
        $this->assertTrue($telegram['find_recipients']);
        $this->assertSame([['token', true]], array_map(static fn (array $field): array => [$field['name'], $field['secret']], $telegram['settings']));
        $this->assertStringContainsString('blockquote', (string) $telegram['markup']);

        $events = $this->resource('notifications://events');
        $order = $events['events'][0];
        $this->assertSame('shop.order', $order['key']);
        $this->assertSame(['items'], $order['lists']);
        $this->assertSame(['customer_email', 'reply_to'], array_column($order['addressees'], 'name'));
        $this->assertSame([1, 1], [$order['channels'], $order['enabled']]);
        $this->assertStringContainsString('Mustache', $events['syntax']['engine']);

        $channels = $this->resource('notifications://channels');
        $this->assertSame(['Orders in Telegram'], array_column($channels['channels'], 'name'));
        $this->assertSame('queue', $channels['mode']);
    }

    private function failedDelivery(Channel $channel): Delivery
    {
        return Delivery::query()->create([
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'provider' => $channel->provider,
            'event' => 'shop.order',
            'recipient' => '100200300',
            'status' => Delivery::FAILED,
            'error' => 'Telegram did not take the message.',
            'attempts' => 3,
            'variables' => ['number' => '1024'],
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->admin(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $response->assertOk();
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(string $uri): array
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                $read = ($resource->handler)();

                return is_array($read) ? $read : [];
            }
        }

        $this->fail("No resource {$uri}.");
    }
}
