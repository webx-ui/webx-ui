<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Settings\Settings;
use WebxUi\Tasks\Models\RunnerBeat;
use WebxUi\Tasks\Notifications\NotificationSettings;
use WebxUi\Tasks\Runner\RunnerSettings;
use WebxUi\Tasks\Tests\Fixtures\Scoped;
use WebxUi\Tasks\Workflow\StageStatus;

/**
 * The watcher's side (§14): its settings, the pause, its heartbeat and the alarm when it goes
 * silent — with the notice leaving through a `database` queue, the way the panel's stand runs it.
 */
final class RunnerTest extends TestCase
{
    private const API = 'https://telegram.test';

    #[Test]
    public function the_watcher_reads_what_the_panel_says_over_the_defaults(): void
    {
        $config = $this->content($this->tool('tasks_runner_config', [], $this->runner()))['config'];

        $this->assertSame(2, $config['max_workers']);
        $this->assertFalse($config['paused']);
        $this->assertTrue($config['runners']['claude']['enabled']);
        // A second runner is something a person switches on, never a default.
        $this->assertFalse($config['runners']['agy']['enabled']);

        $this->app->make(Settings::class)->save([
            'tasks.runner.max_workers' => 4,
            'tasks.runner.daily_sessions' => 0,
            'tasks.runner.agy.enabled' => true,
            'tasks.runner.agy.model' => ' some-model ',
            'tasks.runner.agy.args' => '--yes --note "two words"',
        ]);

        $config = $this->content($this->tool('tasks_runner_config', [], $this->runner()))['config'];

        $this->assertSame(4, $config['max_workers']);
        $this->assertSame(0, $config['daily_sessions']);
        $this->assertSame(['enabled' => true, 'model' => 'some-model', 'args' => ['--yes', '--note', 'two words']], $config['runners']['agy']);
    }

    #[Test]
    public function the_settings_screen_shows_and_saves_the_watcher(): void
    {
        $manager = $this->member(['tasks.view', 'tasks.manage']);

        $values = $this->actingAs($manager, 'cms')->getJson('/api/cms/tasks/settings')->assertOk()->json('data.values');
        $this->assertFalse($values['tasks.runner.paused']);
        $this->assertTrue($values['tasks.notify.runner_silent']);

        $saved = $this->actingAs($manager, 'cms')->putJson('/api/cms/tasks/settings', ['values' => [
            ...$values,
            'tasks.runner.paused' => true,
            'tasks.runner.claude.args' => '--model x',
        ]])->assertOk()->json('data.values');

        $this->assertTrue($saved['tasks.runner.paused']);
        $this->assertSame('--model x', $saved['tasks.runner.claude.args']);

        $this->assertTrue($this->app->make(RunnerSettings::class)->paused());
        $this->assertSame(['--model', 'x'], $this->app->make(RunnerSettings::class)->all()['runners']['claude']['args']);
    }

    #[Test]
    public function a_paused_queue_hands_out_nothing_and_says_why(): void
    {
        $task = $this->task();
        $this->app->make(Settings::class)->save([RunnerSettings::PAUSED => true]);

        $answer = $this->content($this->tool('tasks_claim_next', ['agent' => 'worker-1'], $this->runner()));

        $this->assertNull($answer['claimed']);
        $this->assertTrue($answer['paused']);
        $this->assertStringContainsString('paused', $answer['reason']);
        $this->assertSame(StageStatus::Ready, $this->stage($task, 1)->fresh()?->status);

        $this->app->make(Settings::class)->save([RunnerSettings::PAUSED => false]);

        $claimed = $this->content($this->tool('tasks_claim_next', ['agent' => 'worker-1'], $this->runner()))['claimed'];
        $this->assertSame($this->stage($task, 1)->id, $claimed['stage']['id']);
    }

    #[Test]
    public function the_watcher_takes_and_gives_back_but_does_no_stage(): void
    {
        $task = $this->task();
        $runner = $this->runner();

        $claimed = $this->content($this->tool('tasks_claim_next', ['agent' => 'worker-1'], $runner))['claimed'];
        $stage = $claimed['stage']['id'];

        // Its key carries `tasks:run` only: the tools of a session are not its to call.
        foreach ([
            ['tasks_stage_report', ['stage' => $stage, 'report' => 'Done.', 'agent' => 'worker-1']],
            ['tasks_stage_block', ['stage' => $stage, 'question' => 'Which?', 'agent' => 'worker-1']],
            ['tasks_stage_accept', ['stage' => $stage, 'agent' => 'reviewer-1']],
            ['tasks_get', ['task' => $task->id]],
        ] as [$tool, $arguments]) {
            $this->tool($tool, $arguments, $runner)->assertHasErrors();
        }

        $this->content($this->tool('tasks_stage_release', ['stage' => $stage, 'reason' => 'The session ended.', 'agent' => 'taskd'], $runner));
        $this->assertSame(StageStatus::Ready, $this->stage($task, 1)->fresh()?->status);
    }

    #[Test]
    public function the_heartbeat_is_kept_and_shown_on_the_screen(): void
    {
        $beat = $this->content($this->tool('tasks_runner_heartbeat', [
            'windows' => [['agent' => 'worker-7', 'stage' => 12, 'runner' => 'claude', 'since' => '2026-11-23T10:00:00+00:00']],
            'paused' => false,
            'waiting' => 'No admission: memory',
            'daily_sessions' => 5,
            'version' => '1.4.0',
            'hard_max_workers' => 3,
            'max_workers' => 2,
        ], $this->runner()))['beat'];

        $this->assertSame('taskd', $beat['runner']);
        $this->assertFalse($beat['silent']);
        $this->assertSame('worker-7', $beat['windows'][0]['agent']);

        $this->actingAs($this->member(['tasks.view']), 'cms')
            ->getJson('/api/cms/tasks/runner')
            ->assertOk()
            ->assertJsonPath('data.runners.0.runner', 'taskd')
            ->assertJsonPath('data.runners.0.waiting', 'No admission: memory')
            ->assertJsonPath('data.runners.0.hard_max_workers', 3)
            ->assertJsonPath('data.runners.0.silent', false)
            ->assertJsonPath('data.silent_after_minutes', 5);

        $this->actingAs($this->member([]), 'cms')->getJson('/api/cms/tasks/runner')->assertForbidden();

        $this->travel(6)->minutes();

        $status = $this->content($this->tool('tasks_runner_status', [], $this->holder(['tasks.view'], ['tasks:read'])));
        $this->assertTrue($status['runners'][0]['silent']);
    }

    #[Test]
    public function a_silent_watcher_is_told_once_through_the_database_queue(): void
    {
        $this->useDatabaseQueue();
        $this->app['config']->set('webx-tasks.telegram.api', self::API);
        Http::fake([self::API.'/*' => Http::response(['ok' => true])]);
        $this->app->make(NotificationSettings::class)->setToken('secret-token');
        $this->app->make(Settings::class)->save([NotificationSettings::CHATS => '111']);

        $this->content($this->tool('tasks_runner_heartbeat', ['windows' => []], $this->runner()));

        Artisan::call('webx:tasks:runner-watch');
        $this->assertSame(0, DB::table('jobs')->count(), 'A watcher that beat a minute ago is not silent.');

        $this->travel(6)->minutes();
        Artisan::call('webx:tasks:runner-watch');
        Artisan::call('webx:tasks:runner-watch');

        // Queued, not sent: the scheduler does not wait for Telegram.
        $this->assertSame(1, DB::table('jobs')->count());
        Http::assertNothingSent();

        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

        $this->assertSame(0, DB::table('jobs')->count());
        Http::assertSentCount(1);
        Http::assertSent(static fn (Request $request): bool => str_contains((string) $request['text'], 'taskd')
            && str_contains((string) $request['text'], '/cms/tasks/agents'));
        $this->assertNotNull(RunnerBeat::query()->sole()->silent_notified_at);

        // A beat ends the silence and re-arms the alarm.
        $this->content($this->tool('tasks_runner_heartbeat', ['windows' => []], $this->runner()));
        $this->assertNull(RunnerBeat::query()->sole()->silent_notified_at);
    }

    #[Test]
    public function a_dry_run_of_the_heartbeat_keeps_nothing(): void
    {
        $this->content($this->tool('tasks_runner_heartbeat', ['windows' => [], 'dry_run' => true], $this->runner()));

        $this->assertSame(0, RunnerBeat::query()->count());
    }

    private function useDatabaseQueue(): void
    {
        $this->app['config']->set('queue.default', 'database');
        $this->app['config']->set('queue.connections.database', [
            'driver' => 'database',
            'connection' => 'testing',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ]);

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', static function (Blueprint $table): void {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }

    /** The watcher's account as `webx:tasks:runner` makes it, calling with its key's one scope. */
    private function runner(): CmsUser
    {
        $user = CmsUser::query()->where('name', 'taskd')->first()
            ?? $this->member(['tasks.run', 'tasks.work', 'tasks.review']);

        $user->forceFill(['name' => 'taskd'])->save();
        $user->withAccessToken(new Scoped(['tasks:run']));

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $scopes
     */
    private function holder(array $permissions, array $scopes): CmsUser
    {
        $user = $this->member($permissions);
        $user->withAccessToken(new Scoped($scopes));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function tool(string $tool, array $arguments, CmsUser $as): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as, 'cms')->tool($bound, $arguments);
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
