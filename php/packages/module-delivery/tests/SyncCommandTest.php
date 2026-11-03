<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Delivery\Doctor\SyncCheck;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\SyncDirectory;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Delivery\Testing\FakeCarrier;

final class SyncCommandTest extends SyncTestCase
{
    #[Test]
    public function the_command_syncs_localities_then_points_and_says_how_it_went(): void
    {
        $this->artisan('webx:delivery:sync', ['carrier' => FakeCarrier::KEY])
            ->expectsOutputToContain('carrier-a localities: succeeded — 1 page, 22 seen, 22 new, 0 updated, 0 removed')
            ->expectsOutputToContain('carrier-a points: succeeded — 3 pages, 60 seen, 60 new, 0 updated, 0 removed')
            ->assertSuccessful();

        $this->assertSame(['command', 'command'], DeliverySyncRun::query()->orderBy('id')->get()->map(fn (DeliverySyncRun $run): string => $run->trigger->value)->all());
    }

    #[Test]
    public function without_a_carrier_every_carrier_that_is_on_syncs(): void
    {
        $this->artisan('webx:delivery:sync', ['--directory' => ['localities']])->assertSuccessful();

        $this->assertSame(['localities'], DeliverySyncRun::query()->pluck('directory')->map(fn (Directory $directory): string => $directory->value)->all());
    }

    #[Test]
    public function points_do_not_follow_failed_localities_and_the_command_fails(): void
    {
        $this->carrier->failOnPage(Directory::Localities, 1);

        $this->artisan('webx:delivery:sync', ['carrier' => FakeCarrier::KEY])
            ->expectsOutputToContain('The carrier did not answer')
            ->expectsOutputToContain('points not synced')
            ->assertFailed();

        $this->assertSame(0, DeliverySyncRun::query()->where('directory', 'points')->count());
    }

    #[Test]
    public function a_held_pass_is_said_and_is_not_a_failure(): void
    {
        $this->night();
        $this->carrier->returnNothing(Directory::Points);

        $this->artisan('webx:delivery:sync', ['carrier' => FakeCarrier::KEY, '--directory' => ['points']])
            ->expectsOutputToContain('Held: the carrier returned 60 fewer records')
            ->assertSuccessful();
    }

    #[Test]
    public function a_directory_or_trigger_that_does_not_exist_is_refused(): void
    {
        $this->artisan('webx:delivery:sync', ['--directory' => ['roads']])->assertFailed();
        $this->artisan('webx:delivery:sync', ['--trigger' => 'cron'])->assertFailed();
        $this->artisan('webx:delivery:sync', ['carrier' => 'carrier-z'])->assertFailed();
    }

    #[Test]
    public function queued_the_run_goes_page_by_page_and_the_points_follow(): void
    {
        $this->artisan('webx:delivery:sync', ['carrier' => FakeCarrier::KEY, '--queue' => true, '--trigger' => 'schedule'])
            ->expectsOutputToContain('carrier-a: localities, points queued.')
            ->assertSuccessful();

        $runs = DeliverySyncRun::query()->orderBy('id')->get();
        $this->assertSame([RunStatus::Succeeded, RunStatus::Succeeded], $runs->map(fn (DeliverySyncRun $run): RunStatus => $run->status)->all());
        $this->assertSame([Trigger::Schedule, Trigger::Schedule], $runs->map(fn (DeliverySyncRun $run): Trigger => $run->trigger)->all());
        $this->assertSame(60, $this->points());
    }

    #[Test]
    public function the_queue_tries_a_failed_page_again_and_its_last_failure_closes_the_run(): void
    {
        $this->sync(Directory::Localities);
        $run = $this->app->make(Synchronizer::class)->start(FakeCarrier::KEY, Directory::Points, Trigger::Panel);
        $this->carrier->failOnPage(Directory::Points, 1);
        $job = new SyncDirectory(FakeCarrier::KEY, ['points'], 'panel', $run->id);

        // The site's defaults: three tries, after 60, 300 and 900 seconds (§7.6).
        $defaults = require __DIR__.'/../config/webx-delivery.php';
        $this->assertSame([3, [60, 300, 900]], [$job->tries, $defaults['sync']['backoff']]);

        try {
            $this->app->call([$job, 'handle']);
            $this->fail('The page did not throw for the queue to try again.');
        } catch (CarrierUnavailable) {
            $this->assertTrue($run->refresh()->isRunning());
        }

        $job->failed(new CarrierUnavailable('HTTP 503'));

        $this->assertSame([RunStatus::Failed, 'The carrier did not answer'], [$run->refresh()->status, $run->error]);
    }

    #[Test]
    public function a_second_queued_run_is_answered_with_the_page_of_the_first(): void
    {
        $this->app->make(Synchronizer::class)->start(FakeCarrier::KEY, Directory::Localities, Trigger::Panel);
        Bus::fake();

        $this->artisan('webx:delivery:sync', ['carrier' => FakeCarrier::KEY, '--queue' => true])
            ->expectsOutputToContain('Already running, page 1')
            ->assertSuccessful();

        Bus::assertNotDispatched(SyncDirectory::class);
    }

    #[Test]
    public function the_schedule_runs_a_carriers_directories_by_their_cron(): void
    {
        $events = $this->events();

        $this->assertCount(1, $events);
        $this->assertSame('30 3 * * *', $events[0]->expression);
        $this->assertStringEndsWith('webx:delivery:sync carrier-a --directory=localities --directory=points --trigger=schedule --queue', str_replace("'", '', (string) $events[0]->command));
    }

    #[Test]
    public function a_site_moves_a_directory_to_its_own_cron_or_to_manual(): void
    {
        $this->app['config']->set('webx-delivery.carriers.carrier-a.schedule.points', '0 5 * * 1');
        $events = $this->events();

        $this->assertSame(['30 3 * * *', '0 5 * * 1'], array_map(static fn (Event $event): string => $event->expression, $events));
        $this->assertStringEndsWith('carrier-a --directory=points --trigger=schedule --queue', str_replace("'", '', (string) $events[1]->command));

        $this->app['config']->set('webx-delivery.carriers.carrier-a.schedule.points', 'manual');
        $this->assertCount(1, $this->events());
    }

    #[Test]
    public function the_doctor_says_a_stale_or_held_directory(): void
    {
        if (! interface_exists(Check::class)) {
            $this->markTestSkipped('webx:doctor comes with the panel\'s frame.');
        }

        $check = $this->app->make(SyncCheck::class);
        $this->assertSame(['carrier-a localities has never synced', 'carrier-a points has never synced'], $this->lines($check->run()));

        $this->night();
        $this->assertSame([Diagnosis::OK], array_map(static fn (Diagnosis $diagnosis): string => $diagnosis->state, $check->run()));

        // Daily at night: three days without a success is stale.
        $this->travel(4)->days();
        $this->assertSame(['carrier-a localities last synced 4 days before', 'carrier-a points last synced 4 days before'], $this->lines($check->run()));

        $this->carrier->returnNothing(Directory::Points);
        $this->night();
        $found = $check->run();
        $this->assertCount(1, $found);
        $this->assertTrue($found[0]->content, 'mended on the carriers screen, not a reason to stop a deploy');
        $this->assertStringContainsString('carrier-a points: the last pass is held — the carrier returned 60 fewer records', $found[0]->detail);
    }

    /** @return list<Event> */
    private function events(): array
    {
        $this->app->forgetInstance(Schedule::class);
        $schedule = $this->app->make(Schedule::class);

        return array_values(array_filter($schedule->events(), static fn (Event $event): bool => str_contains((string) $event->command, 'webx:delivery:sync')));
    }

    /**
     * @param  list<Diagnosis>  $found
     * @return list<string>
     */
    private function lines(array $found): array
    {
        return array_map(static fn (Diagnosis $diagnosis): string => trim(explode('(', $diagnosis->detail)[0]), $found);
    }
}
