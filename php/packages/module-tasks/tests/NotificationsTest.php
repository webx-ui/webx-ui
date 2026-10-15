<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Settings\Models\Setting;
use WebxUi\Settings\Settings;
use WebxUi\Tasks\Models\JournalEntry;
use WebxUi\Tasks\Notifications\NotificationSettings;
use WebxUi\Tasks\Notifications\Notifier;
use WebxUi\Tasks\Notifications\SendNotice;
use WebxUi\Tasks\Workflow\ActorType;
use WebxUi\Tasks\Workflow\Journal;
use WebxUi\Tasks\Workflow\JournalKind;
use WebxUi\Tasks\Workflow\StageStatus;

/**
 * The notices of §6a, as §10 asks: an event queues a job with the right words; an event switched
 * off sends nothing; a delivery that fails leaves the move done and says so in the journal; and the
 * bot's token is nowhere to be read.
 */
final class NotificationsTest extends TestCase
{
    private const API = 'https://telegram.test';

    private ?QueueFake $queue = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-tasks.telegram.api', self::API);
        Http::preventStrayRequests();
    }

    #[Test]
    public function a_stage_waiting_for_a_person_queues_a_short_message_with_the_way_to_the_task(): void
    {
        $this->configure();
        $this->queue = Queue::fake();

        $task = $this->task(stages: ['Schema', 'Design'], human: ['Design']);
        $one = $this->claim('worker-1');
        $this->tasks()->report($one, 'Done.', null, $this->agent('worker-1'));
        $this->tasks()->accept($one, $this->agent('dispatcher'));

        $two = $this->claim('worker-2');
        $this->tasks()->report($two, "## Done\nThe screens are in, both of them.\n\n## How to check\nOpen the cart.", 'feat/cart@abc123', $this->agent('worker-2'));

        $notices = $this->queued();
        $this->assertSame(['stage.review_human'], array_column($notices, 'event'));

        $text = $notices[0]['text'];
        $this->assertStringStartsWith('⏳ shop#1 · Shop — Cart page', $text);
        $this->assertStringContainsString('Stage 2/2 «Design» waits for your review (attempt 1)', $text);
        $this->assertStringContainsString('The screens are in, both of them.', $text);
        $this->assertStringNotContainsString('How to check', $text);
        $this->assertStringContainsString('Result: feat/cart@abc123', $text);
        $this->assertStringContainsString("https://tasks.example.test/cms/tasks/{$task->id}", $text);
    }

    #[Test]
    public function a_question_and_a_finished_task_are_told_and_a_switched_off_event_is_not(): void
    {
        $this->configure();
        $this->queue = Queue::fake();

        $this->task(stages: ['One']);
        $stage = $this->claim('worker-1');
        $this->tasks()->block($stage, 'Which payment provider?', $this->agent('worker-1'));
        $this->tasks()->unblock($stage, 'The configured one.', $this->person());

        // Sending back is off by default; the dispatcher's own review too.
        $stage = $this->claim('worker-2');
        $this->tasks()->report($stage, 'Done.', null, $this->agent('worker-2'));
        $this->tasks()->rework($stage, 'Again.', $this->agent('dispatcher'));
        $stage = $this->claim('worker-3');
        $this->tasks()->report($stage, 'Done.', null, $this->agent('worker-3'));
        $this->tasks()->accept($stage, $this->agent('dispatcher'));

        $notices = $this->queued();
        $this->assertSame(['stage.blocked', 'task.done'], array_column($notices, 'event'));
        $this->assertStringContainsString('Which payment provider?', $notices[0]['text']);
        $this->assertStringContainsString('The task is done. Stages accepted: 1', $notices[1]['text']);

        $this->queue = Queue::fake();
        $this->app->make(Settings::class)->save(['tasks.notify.blocked' => false]);
        $this->task(stages: ['Two']);
        $stage = $this->claim('worker-4');
        $this->tasks()->block($stage, 'Again?', $this->agent('worker-4'));

        $this->assertSame([], $this->queued());
    }

    #[Test]
    public function nothing_is_queued_without_a_token_and_a_chat(): void
    {
        $this->queue = Queue::fake();

        $this->task(stages: ['One']);
        $stage = $this->claim('worker-1');
        $this->tasks()->block($stage, 'Anyone?', $this->agent('worker-1'));

        $this->assertSame([], $this->queued());
    }

    #[Test]
    public function a_move_that_is_rolled_back_announces_nothing(): void
    {
        // A real queue, not a fake: only the queue itself holds a job back until the commit.
        $this->configure();
        Http::fake([self::API.'/*' => Http::response(['ok' => true])]);

        $this->task(stages: ['One']);
        $stage = $this->claim('worker-1');

        Rehearsal::run(fn () => $this->tasks()->block($stage, 'Rehearsed?', $this->agent('worker-1')));

        Http::assertNothingSent();
        $this->assertSame(StageStatus::InProgress, $stage?->refresh()->status);
    }

    #[Test]
    public function the_message_goes_to_every_chat_as_plain_text(): void
    {
        $this->configure(chats: "111\n-222");
        Http::fake([self::API.'/*' => Http::response(['ok' => true])]);

        $this->task(stages: ['One']);
        $stage = $this->claim('worker-1');
        $this->tasks()->block($stage, 'Which?', $this->agent('worker-1'));

        Http::assertSentCount(2);
        Http::assertSent(static fn (ClientRequest $request): bool => $request->url() === self::API.'/botsecret-token/sendMessage'
            && $request['chat_id'] === '-222'
            && str_contains((string) $request['text'], 'Which?')
            && ! isset($request['parse_mode']));
    }

    #[Test]
    public function a_failed_delivery_leaves_the_move_done_and_says_so_in_the_journal(): void
    {
        $this->configure();
        Http::fake([self::API.'/*' => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);

        $task = $this->task(stages: ['One']);
        $stage = $this->claim('worker-1');

        // The sync queue runs the job inside this call: the move must still come back done.
        $stage = $this->tasks()->block($stage, 'Which?', $this->agent('worker-1'));
        $this->assertSame(StageStatus::Blocked, $stage->status);

        $entry = JournalEntry::query()->where('task_id', $task->id)->where('kind', JournalKind::Comment)->sole();
        $this->assertSame(ActorType::System, $entry->actor_type);
        $this->assertStringContainsString('bot was blocked by the user', (string) $entry->body);
        $this->assertStringNotContainsString('secret-token', (string) $entry->body);
        $this->assertSame('stage.blocked', $entry->meta['notice'] ?? null);
    }

    #[Test]
    public function an_attempt_before_the_last_is_retried_rather_than_given_up(): void
    {
        $this->configure();
        Http::fake([self::API.'/*' => Http::response(['ok' => false], 500)]);

        $job = new SendNotice(['event' => 'stage.blocked', 'task_id' => 1, 'stage_id' => null, 'text' => 'Hi']);
        $fake = $job->withFakeQueueInteractions();
        $fake->handle($this->app->make(Notifier::class), $this->app->make(Journal::class));

        $fake->assertReleased(30);
        $this->assertSame(3, $job->tries);
    }

    #[Test]
    public function a_hung_stage_is_reported_once_per_stint(): void
    {
        $this->configure();
        $this->queue = Queue::fake();

        $this->task(stages: ['One']);
        Carbon::setTestNow('2026-10-15 08:00');
        $stage = $this->claim('worker-1');
        Carbon::setTestNow('2026-10-15 13:00');

        $this->artisan('webx:tasks:stale')->assertSuccessful();
        $this->assertSame([], $this->queued(), 'Five hours is inside the limit of six.');

        Carbon::setTestNow('2026-10-15 15:00');
        $this->artisan('webx:tasks:stale')->assertSuccessful();
        $this->artisan('webx:tasks:stale')->assertSuccessful();

        $notices = $this->queued();
        $this->assertSame(['stage.stale'], array_column($notices, 'event'));
        $this->assertStringContainsString('worker-1, hours: 7', implode("\n", array_column($notices, 'text')));

        // Taken again after a release: a new stint, which may hang again.
        $this->tasks()->release($stage, $this->person());
        $this->assertNotNull($this->claim('worker-2'));
        $this->assertNull($stage?->refresh()->stale_notified_at);
        Carbon::setTestNow();
    }

    #[Test]
    public function the_token_is_stored_encrypted_and_read_back_by_nobody(): void
    {
        $planner = $this->member(['tasks.view', 'tasks.manage']);

        $this->actingAs($planner, 'cms')->putJson('/api/cms/tasks/settings', ['values' => [
            'tasks.telegram.token' => 'secret-token',
            'tasks.telegram.chats' => '111',
            'tasks.notify.rework' => true,
        ]])->assertOk()->assertJsonPath('data.token_set', true);

        $stored = (string) json_encode(Setting::query()->where('key', NotificationSettings::TOKEN)->value('value'));
        $this->assertStringNotContainsString('secret-token', $stored);
        $this->assertSame('secret-token', $this->app->make(NotificationSettings::class)->token());

        $read = $this->actingAs($planner, 'cms')->getJson('/api/cms/tasks/settings')->assertOk();
        $this->assertStringNotContainsString('secret-token', (string) $read->getContent());
        $this->assertSame('', $read->json('data.values')['tasks.telegram.token']);
        $this->assertTrue($read->json('data.values')['tasks.notify.rework']);
        $this->assertTrue($read->json('data.values')['tasks.notify.review_human'], 'A switch never saved shows its default.');

        // Through MCP neither: the key is no setting the settings tools know.
        $get = new RegistryTool($this->app->make(ToolRegistry::class)->tool('settings_get'));
        $answer = WebxServer::actingAs($this->admin(), 'cms')->tool($get, ['key' => NotificationSettings::TOKEN]);
        $answer->assertDontSee('secret-token');
        $answer->assertSee('No setting is named');

        // An empty field keeps the token; asking to forget it does.
        $this->actingAs($planner, 'cms')->putJson('/api/cms/tasks/settings', ['values' => ['tasks.telegram.token' => '']])
            ->assertJsonPath('data.token_set', true);
        $this->actingAs($planner, 'cms')->putJson('/api/cms/tasks/settings', ['values' => [], 'forget_token' => true])
            ->assertJsonPath('data.token_set', false);
    }

    #[Test]
    public function the_test_button_sends_now_and_says_what_went_wrong(): void
    {
        $planner = $this->member(['tasks.view', 'tasks.manage']);

        $this->actingAs($planner, 'cms')->postJson('/api/cms/tasks/settings/test')->assertStatus(409);

        $this->configure();
        Http::fake([self::API.'/*' => Http::sequence()
            ->push(['ok' => true])
            ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);

        $this->actingAs($planner, 'cms')->postJson('/api/cms/tasks/settings/test')->assertOk();
        $this->actingAs($planner, 'cms')->postJson('/api/cms/tasks/settings/test')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Telegram did not take the message — 111: Bad Request: chat not found');

        $this->actingAs($this->member(['tasks.view']), 'cms')->postJson('/api/cms/tasks/settings/test')->assertForbidden();
    }

    private function configure(string $chats = '111'): void
    {
        $settings = $this->app->make(NotificationSettings::class);
        $settings->setToken('secret-token');
        $this->app->make(Settings::class)->save([NotificationSettings::CHATS => $chats]);
    }

    /**
     * What was queued, in order.
     *
     * @return list<array{event: string, task_id: int, stage_id: int|null, text: string}>
     */
    private function queued(): array
    {
        return ($this->queue ?? Queue::fake())->pushed(SendNotice::class)->map(static fn (SendNotice $job): array => $job->notice)->values()->all();
    }
}
