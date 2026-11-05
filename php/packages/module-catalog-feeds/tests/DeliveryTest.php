<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Exchange\ExchangeProfile;
use WebxUi\Catalog\Exchange\ExchangeRun;
use WebxUi\Catalog\Storefront\StorefrontCache;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogFeeds\Build\BuildFeed;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Delivery\FeedFiles;
use WebxUi\CatalogFeeds\Delivery\FeedKey;
use WebxUi\CatalogFeeds\Delivery\FeedPrune;
use WebxUi\CatalogFeeds\Delivery\FeedWatch;
use WebxUi\CatalogFeeds\Doctor\FeedsCheck;
use WebxUi\CatalogFeeds\Doctor\RobotsRules;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogStock\StockServiceProvider;
use WebxUi\Routing\Reserved;
use WebxUi\Settings\Settings;

/**
 * The address of a feed and what keeps it fed (§7.3–§7.6, §13 of the feeds spec): what a marketplace
 * gets, what a stranger learns, when a build is started and what is cleaned up.
 */
final class DeliveryTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            BrandsServiceProvider::class,
            LabelsServiceProvider::class,
            StockServiceProvider::class,
            CatalogPropertiesServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Shop::seedOnce($this->app, self::class);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_second_request_is_answered_without_the_file(): void
    {
        $feed = $this->feed();
        $build = $this->built($feed);

        $first = $this->get($this->address($feed));

        $first->assertOk();
        $this->assertSame(Storage::disk('local')->get((string) $build->path), $first->streamedContent());
        $this->assertSame('"'.$build->etag.'"', $first->headers->get('ETag'));
        $this->assertSame('text/plain; charset=utf-8', $first->headers->get('Content-Type'));
        $this->assertSame((string) $build->bytes, $first->headers->get('Content-Length'));
        $this->assertSame('noindex', $first->headers->get('X-Robots-Tag'));

        foreach (['private', 'max-age=0', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, (string) $first->headers->get('Cache-Control'));
        }

        // No file on the disk: a 304 that read it would have nothing to say.
        Storage::disk('local')->delete((string) $build->path);

        $byTag = $this->get($this->address($feed), ['If-None-Match' => (string) $first->headers->get('ETag')]);
        $byDate = $this->get($this->address($feed), ['If-Modified-Since' => (string) $first->headers->get('Last-Modified')]);

        $byTag->assertStatus(304);
        $byDate->assertStatus(304);
        $this->assertSame($first->headers->get('ETag'), $byTag->headers->get('ETag'));
    }

    #[Test]
    public function a_tag_or_a_date_that_is_behind_gets_the_file(): void
    {
        $feed = $this->feed();
        $this->built($feed);

        $this->get($this->address($feed), ['If-None-Match' => '"something-else"'])->assertOk();
        $this->get($this->address($feed), ['If-Modified-Since' => Carbon::now()->subYear()->toRfc7231String()])->assertOk();
    }

    #[Test]
    public function a_rebuild_with_the_same_content_tells_a_marketplace_nothing_changed(): void
    {
        $feed = $this->feed();
        $this->built($feed);
        $first = $this->get($this->address($feed));

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->built($feed);

        $this->get($this->address($feed), ['If-None-Match' => (string) $first->headers->get('ETag')])->assertStatus(304);
        $this->assertSame($first->headers->get('Last-Modified'), $this->get($this->address($feed))->headers->get('Last-Modified'));
    }

    #[Test]
    public function head_says_what_get_would_and_sends_nothing(): void
    {
        $feed = $this->feed();
        $build = $this->built($feed);

        $response = $this->call('HEAD', $this->address($feed));

        $response->assertOk();
        $this->assertSame('', $response->getContent());
        $this->assertSame((string) $build->bytes, $response->headers->get('Content-Length'));
        $this->assertSame('"'.$build->etag.'"', $response->headers->get('ETag'));
    }

    #[Test]
    public function each_format_has_its_extension_and_its_type(): void
    {
        $types = [
            'xml' => ['xml', 'application/xml', '<i>{{id}}</i>', '<r>', '</r>'],
            'json' => ['json', 'application/json', '{"id":"{{id}}"}', '[', ']'],
            'csv' => ['csv', 'text/csv', '{{id}}', null, null],
        ];

        foreach ($types as $format => [$extension, $type, $item, $head, $foot]) {
            $feed = $this->feed(['format' => $format, 'item' => $item, 'head' => $head, 'foot' => $foot, 'separator' => Feed::separatorOf($format)]);
            $this->assertSame(FeedBuild::DONE, $this->built($feed)->status);

            $response = $this->get($this->address($feed));

            $response->assertOk();
            $this->assertStringEndsWith('.'.$extension, $this->address($feed));
            $this->assertSame($type.'; charset=utf-8', $response->headers->get('Content-Type'));
        }
    }

    #[Test]
    public function a_stranger_cannot_tell_a_wrong_key_from_a_right_one(): void
    {
        $off = $this->feed(['is_active' => false]);
        $this->built($this->feed());
        $reissued = $this->feed();
        $this->built($reissued);
        $old = $this->address($reissued);
        $this->app->make(FeedKey::class)->reissue($reissued);

        foreach ([$old, '/feeds/'.str_repeat('x', 40).'.txt', $this->address($off)] as $address) {
            $response = $this->get($address);

            $response->assertNotFound();
            $this->assertSame('', $response->getContent());
            $this->assertFalse($response->headers->has('Retry-After'));
        }
    }

    #[Test]
    public function the_case_of_a_key_counts_whatever_the_database_thinks(): void
    {
        $feed = $this->feed();
        $this->built($feed);

        $this->get('/feeds/'.strtolower($feed->access_key).'.txt')->assertNotFound();
        $this->get('/feeds/'.strtoupper($feed->access_key).'.txt')->assertNotFound();
    }

    #[Test]
    public function only_the_right_key_is_told_to_come_back(): void
    {
        $feed = $this->feed();

        $response = $this->get($this->address($feed));

        $response->assertNotFound();
        $this->assertSame('600', $response->headers->get('Retry-After'));
        $this->assertSame(0, FeedBuild::query()->where('feed_id', $feed->id)->count());
    }

    #[Test]
    public function a_build_the_journal_lost_or_a_file_the_disk_lost_is_no_file(): void
    {
        $feed = $this->feed();
        $build = $this->built($feed);

        Storage::disk('local')->delete((string) $build->path);
        $this->get($this->address($feed))->assertNotFound()->assertHeader('Retry-After', '600');

        $build->delete();
        $this->get($this->address($feed))->assertNotFound()->assertHeader('Retry-After', '600');
    }

    #[Test]
    public function a_format_changed_moves_the_address_and_the_old_file_is_not_passed_off_as_the_new(): void
    {
        $feed = $this->feed(['format' => 'xml', 'item' => '<i>{{id}}</i>', 'head' => '<r>', 'foot' => '</r>']);
        $this->built($feed);
        $old = $this->address($feed);

        $feed->forceFill(['format' => 'text', 'head' => null, 'foot' => null])->save();

        $this->get($old)->assertStatus(301)->assertRedirect($feed->refresh()->address());
        $this->get($this->address($feed))->assertNotFound()->assertHeader('Retry-After', '600');

        $this->built($feed);
        $this->get($this->address($feed))->assertOk();
    }

    #[Test]
    public function a_failed_build_leaves_the_file_that_was_served(): void
    {
        $feed = $this->feed();
        $good = $this->built($feed);
        $before = $this->get($this->address($feed))->streamedContent();

        $feed->forceFill(['format' => 'xml', 'item' => '<broken>{{id}}', 'head' => '<r>', 'foot' => '</r>'])->save();
        $failed = $this->built($feed);
        $feed->forceFill(['format' => 'text'])->save();

        $this->assertSame(FeedBuild::FAILED, $failed->status);
        $this->assertSame($good->id, $feed->refresh()->current_build_id);
        $this->assertSame($before, $this->get($this->address($feed))->streamedContent());
    }

    #[Test]
    public function a_robot_is_held_back_by_key_and_address_and_nobody_else_is(): void
    {
        $this->app['config']->set('webx-catalog-feeds.per_minute', 3);
        $first = $this->feed();
        $second = $this->feed();
        $this->built($first);
        $this->built($second);

        foreach (range(1, 3) as $request) {
            $this->get($this->address($first))->assertOk();
        }

        $this->get($this->address($first))->assertStatus(429);
        $this->get($this->address($second))->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])->get($this->address($first))->assertOk();
    }

    #[Test]
    public function the_address_carries_no_cookie_and_no_page_may_take_its_prefix(): void
    {
        $feed = $this->feed();
        $this->built($feed);

        $this->assertSame([], $this->get($this->address($feed))->headers->getCookies());
        $this->assertTrue($this->app->make(Reserved::class)->taken('feeds/anything'));
    }

    #[Test]
    public function a_closed_site_closes_the_address_until_it_is_opened(): void
    {
        $feed = $this->feed();
        $this->built($feed);
        $this->app['config']->set('webx-admin.gate.enabled', true);
        $this->app['config']->set('webx-admin.gate.users', 'tester:secret');

        $this->get($this->address($feed))->assertStatus(401);
        $this->assertStringContainsString('closed by its password', $this->said());

        $this->app['config']->set('webx-admin.gate.except', ['feeds/*']);

        $this->get($this->address($feed))->assertOk();
        $this->assertStringNotContainsString('closed by its password', $this->said());
    }

    #[Test]
    public function a_reissued_key_closes_the_old_address_and_opens_the_new_at_once(): void
    {
        $feed = $this->feed();
        $build = $this->built($feed);
        $old = $this->address($feed);
        $oldKey = $feed->access_key;
        $issued = $feed->key_issued_at;
        $this->get($old)->assertOk();

        Carbon::setTestNow(Carbon::now()->addMinute());
        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api($feed->id.'/reissue-key'));

        $response->assertOk();
        $feed->refresh();
        $this->assertNotSame($oldKey, $feed->access_key);
        $this->assertSame(40, strlen($feed->access_key));
        $this->assertTrue($feed->key_issued_at->gt($issued));
        $this->assertSame($feed->address(), $response->json('data.address'));

        $this->get($old)->assertNotFound();
        $this->get($this->address($feed))->assertOk();
        // Nothing was built and nothing moved: no file has the key in its path.
        $this->assertSame($build->id, $feed->current_build_id);
        $this->assertSame(1, FeedBuild::query()->where('feed_id', $feed->id)->count());

        $rows = HistoryEntry::query()->where('subject_type', Feed::TYPE)->where('subject_id', $feed->id)->where('event', '!=', HistoryEntry::CREATED)->get();

        $this->assertCount(1, $rows);
        $this->assertSame(HistoryEntry::KEY_REISSUED, $rows[0]->event);
        $this->assertSame('panel', $rows[0]->source);
        $this->assertNotSame('', $rows[0]->admin_name);

        $row = (string) json_encode($rows[0]->getAttributes());
        $this->assertStringNotContainsString($oldKey, $row);
        $this->assertStringNotContainsString($feed->access_key, $row);
    }

    #[Test]
    public function reissuing_a_key_asks_for_manage(): void
    {
        $feed = $this->feed();
        $key = $feed->access_key;

        $this->actingAs($this->editor(['catalog.view']), 'cms')->postJson($this->api($feed->id.'/reissue-key'))->assertForbidden();

        $this->assertSame($key, $feed->refresh()->access_key);
    }

    #[Test]
    public function an_import_in_ten_batches_gives_one_build(): void
    {
        Queue::fake();
        $feed = $this->watched();
        $run = $this->importRun();
        $now = Carbon::parse('2026-11-05 12:00:00');

        // Ten batches, three minutes apart: half an hour in all, three times the silence a build
        // waits for, and the catalogue never falls silent for it.
        foreach (range(1, 10) as $batch) {
            $this->app->make(StorefrontCache::class)->bump();

            foreach (range(1, 3) as $minute) {
                $this->tickAt($now = $now->copy()->addMinute());
            }
        }

        $this->assertSame(0, $this->builds($feed));

        // The last batch is in; the run is still closing, and a build waits for it.
        $this->tickAt($now = $now->copy()->addMinutes(9));
        $this->assertSame(0, $this->builds($feed));

        $run->forceFill(['status' => ExchangeRun::DONE])->save();

        foreach (range(1, 15) as $minute) {
            $this->tickAt($now = $now->copy()->addMinute());
        }

        $this->assertSame(1, $this->builds($feed));
        $this->assertSame(FeedBuild::CHANGE, FeedBuild::query()->where('feed_id', $feed->id)->value('trigger'));
        Queue::assertPushed(BuildFeed::class, 1);
    }

    #[Test]
    public function a_build_done_settles_the_catalogue_and_nothing_follows_it(): void
    {
        $feed = $this->watched();
        $now = Carbon::parse('2026-11-05 12:00:00');

        $this->tickAt($now);
        $this->tickAt($now = $now->copy()->addMinutes(10));

        $this->assertSame(1, $this->builds($feed));
        $feed->refresh();
        $this->assertNull($feed->dirty_since);
        $this->assertNull($feed->first_dirty_at);
        $this->assertSame((string) $this->app->make(StorefrontCache::class)->generation(), $feed->built_generation);

        $this->tickAt($now->copy()->addHours(5));
        $this->assertSame(1, $this->builds($feed));
    }

    #[Test]
    public function a_catalogue_that_never_falls_quiet_is_built_at_the_end_of_the_wait(): void
    {
        Queue::fake();
        $feed = $this->watched();
        $now = Carbon::parse('2026-11-05 12:00:00');

        // A change every five minutes, for six hours less a minute: silence never reaches ten.
        for ($minute = 0; $minute < 359; $minute += 1) {
            if ($minute % 5 === 0) {
                $this->app->make(StorefrontCache::class)->bump();
            }

            $this->tickAt($now->copy()->addMinutes($minute));
        }

        $this->assertSame(0, $this->builds($feed));

        $this->tickAt($now->copy()->addMinutes(360));
        $this->assertSame(1, $this->builds($feed));
    }

    #[Test]
    public function an_import_stuck_for_good_holds_a_build_no_longer_than_the_wait(): void
    {
        Queue::fake();
        $feed = $this->watched();
        $this->importRun();
        $now = Carbon::parse('2026-11-05 12:00:00');

        $this->tickAt($now);
        $this->tickAt($now->copy()->addMinutes(359));
        $this->assertSame(0, $this->builds($feed));

        $this->tickAt($now->copy()->addMinutes(360));
        $this->assertSame(1, $this->builds($feed));
    }

    #[Test]
    public function a_rehearsal_of_an_import_holds_nothing(): void
    {
        Queue::fake();
        $feed = $this->watched();
        $this->importRun(['dry_run' => true]);
        $now = Carbon::parse('2026-11-05 12:00:00');

        $this->tickAt($now);
        $this->tickAt($now->copy()->addMinutes(10));

        $this->assertSame(1, $this->builds($feed));
    }

    #[Test]
    public function changes_after_a_build_wait_for_the_interval_however_quiet_the_catalogue_is(): void
    {
        $feed = $this->watched();
        $now = Carbon::parse('2026-11-05 12:00:00');

        $this->tickAt($now);
        $this->tickAt($now->copy()->addMinutes(10));
        $this->assertSame(1, $this->builds($feed));

        $this->app->make(StorefrontCache::class)->bump();
        $this->tickAt($now->copy()->addMinutes(11));
        $this->tickAt($now->copy()->addMinutes(69));
        $this->assertSame(1, $this->builds($feed));

        $this->tickAt($now->copy()->addMinutes(70));
        $this->assertSame(2, $this->builds($feed));
    }

    #[Test]
    public function a_feed_without_the_flag_does_not_follow_the_catalogue(): void
    {
        Queue::fake();
        $feed = $this->feed();
        $now = Carbon::parse('2026-11-05 12:00:00');

        $this->app->make(StorefrontCache::class)->bump();
        $this->tickAt($now);
        $this->tickAt($now->copy()->addDays(2));

        $this->assertSame(0, $this->builds($feed));
        $this->assertNull($feed->refresh()->dirty_since);
    }

    #[Test]
    public function a_feed_switched_off_is_not_watched(): void
    {
        Queue::fake();
        $feed = $this->feed(['schedule' => 'hourly', 'is_active' => false]);

        $this->tickAt(Carbon::parse('2026-11-05 12:00:00'));

        $this->assertSame(0, $this->builds($feed));
    }

    #[Test]
    public function an_hourly_feed_comes_every_hour_and_a_late_second_does_not_put_it_off(): void
    {
        Queue::fake();
        $feed = $this->feed(['schedule' => 'hourly']);

        $this->tickAt(Carbon::parse('2026-11-05 12:00:30'));
        $this->assertSame(1, $this->builds($feed));
        $this->finish($feed);

        $this->tickAt(Carbon::parse('2026-11-05 12:59:00'));
        $this->assertSame(1, $this->builds($feed));

        $this->tickAt(Carbon::parse('2026-11-05 13:00:00'));
        $this->assertSame(2, $this->builds($feed));
        $this->assertSame(FeedBuild::SCHEDULE, FeedBuild::query()->where('feed_id', $feed->id)->orderByDesc('id')->value('trigger'));
    }

    #[Test]
    public function every_six_hours_is_six_hours(): void
    {
        Queue::fake();
        $feed = $this->feed(['schedule' => 'every_6_hours']);

        $this->tickAt(Carbon::parse('2026-11-05 12:00:00'));
        $this->finish($feed);
        $this->tickAt(Carbon::parse('2026-11-05 17:59:00'));
        $this->assertSame(1, $this->builds($feed));

        $this->tickAt(Carbon::parse('2026-11-05 18:00:00'));
        $this->assertSame(2, $this->builds($feed));
    }

    #[Test]
    public function a_daily_feed_comes_at_its_hour_and_catches_up_after_a_night_without_a_server(): void
    {
        Queue::fake();
        $feed = $this->feed(['schedule' => 'daily', 'schedule_at' => '03:30']);

        FeedBuild::query()->create(['feed_id' => $feed->id, 'status' => FeedBuild::DONE, 'trigger' => FeedBuild::SCHEDULE, 'queued_at' => '2026-11-05 03:30:00']);

        $this->tickAt(Carbon::parse('2026-11-06 03:29:00'));
        $this->assertSame(1, $this->builds($feed));

        $this->tickAt(Carbon::parse('2026-11-06 03:30:00'));
        $this->assertSame(2, $this->builds($feed));
        $this->finish($feed);

        $this->tickAt(Carbon::parse('2026-11-06 03:31:00'));
        $this->assertSame(2, $this->builds($feed));

        // The night of the 7th passed with nothing running; a morning look makes up for it.
        $this->tickAt(Carbon::parse('2026-11-08 02:00:00'));
        $this->assertSame(3, $this->builds($feed));
    }

    #[Test]
    public function a_build_that_keeps_failing_is_tried_once_a_period(): void
    {
        $feed = $this->feed(['schedule' => 'hourly', 'item' => '']);

        $this->tickAt(Carbon::parse('2026-11-05 12:00:00'));
        $this->tickAt(Carbon::parse('2026-11-05 12:01:00'));
        $this->tickAt(Carbon::parse('2026-11-05 12:59:00'));

        $this->assertSame(1, $this->builds($feed));
        $this->assertSame(FeedBuild::FAILED, FeedBuild::query()->where('feed_id', $feed->id)->value('status'));
    }

    #[Test]
    public function the_watcher_is_on_the_schedule_and_writes_nothing_into_the_journal(): void
    {
        Queue::fake();
        $feed = $this->watched();
        $updated = $feed->updated_at;

        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        $this->artisan('webx:catalog:feeds')->assertSuccessful();

        $this->assertNotNull($feed->refresh()->dirty_since);
        $this->assertEquals($updated, $feed->updated_at);
        $this->assertSame(0, HistoryEntry::query()->where('subject_id', $feed->id)->where('subject_type', Feed::TYPE)->where('event', HistoryEntry::UPDATED)->count());

        $commands = array_map(static fn (Event $event): string => (string) $event->command, $this->app->make(Schedule::class)->events());

        $this->assertNotEmpty(array_filter($commands, static fn (string $command): bool => str_contains($command, 'webx:catalog:feeds') && ! str_contains($command, 'prune')));
        $this->assertNotEmpty(array_filter($commands, static fn (string $command): bool => str_contains($command, 'webx:catalog:feeds-prune')));
    }

    #[Test]
    public function the_file_served_and_those_before_it_stay_and_the_rest_go(): void
    {
        $feed = $this->feed();
        $builds = [];

        foreach (range(1, 5) as $number) {
            $feed->forceFill(['item' => $number.':{{id}}'])->save();
            $builds[] = $this->built($feed);
        }

        $this->artisan('webx:catalog:feeds-prune')->assertSuccessful();

        $disk = Storage::disk('local');

        $disk->assertMissing((string) $builds[0]->path);
        $disk->assertMissing((string) $builds[1]->path);
        $disk->assertExists((string) $builds[2]->path);
        $disk->assertExists((string) $builds[3]->path);
        $disk->assertExists((string) $builds[4]->path);
        $this->assertNull($builds[0]->refresh()->path);
        $this->assertSame($builds[4]->path, $builds[4]->refresh()->path);
        $this->get($this->address($feed))->assertOk();
    }

    #[Test]
    public function the_build_served_outlives_the_journal_and_a_build_under_way_is_left_alone(): void
    {
        Queue::fake();
        $feed = $this->feed();
        $disk = Storage::disk('local');
        $old = Carbon::now()->subDays(90);

        $served = $this->finished($feed, ['finished_at' => $old, 'path' => 'catalog-feeds/'.$feed->id.'/9001.txt']);
        $disk->put((string) $served->path, 'served');
        $feed->forceFill(['current_build_id' => $served->id])->save();

        $failed = $this->finished($feed, ['status' => FeedBuild::FAILED, 'finished_at' => $old]);
        $recent = $this->finished($feed, ['status' => FeedBuild::FAILED, 'finished_at' => Carbon::now()->subDay()]);
        $going = $this->app->make(FeedBuilder::class)->start($feed);
        $this->assertInstanceOf(FeedBuild::class, $going);

        $temporary = $this->app->make(FeedFiles::class)->temporaryDirectory();
        @mkdir($temporary, 0775, true);
        $alive = $temporary.'/'.$going->id.'.body';
        $dead = $temporary.'/'.$failed->id.'.body';
        touch($alive);
        touch($dead);

        $this->app->make(FeedPrune::class)->run();

        $this->assertNotNull(FeedBuild::query()->find($served->id));
        $disk->assertExists((string) $served->path);
        $this->assertNull(FeedBuild::query()->find($failed->id));
        $this->assertNotNull(FeedBuild::query()->find($recent->id));
        $this->assertNotNull(FeedBuild::query()->find($going->id));
        $this->assertFileExists($alive);
        $this->assertFileDoesNotExist($dead);

        unlink($alive);
    }

    #[Test]
    public function a_feed_deleted_takes_its_files_and_a_lost_directory_is_swept(): void
    {
        $feed = $this->feed();
        $build = $this->built($feed);
        $address = $this->address($feed);
        $disk = Storage::disk('local');
        $disk->put('catalog-feeds/987654/1.txt', 'left by a snapshot');

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api((string) $feed->id))->assertNoContent();

        $disk->assertMissing((string) $build->path);
        $this->get($address)->assertNotFound();

        $this->app->make(FeedPrune::class)->run();

        $disk->assertMissing('catalog-feeds/987654/1.txt');
    }

    #[Test]
    public function the_doctor_knows_the_check_and_it_says_nothing_of_a_healthy_feed(): void
    {
        $this->assertContains(FeedsCheck::class, $this->app->make(DoctorChecks::class)->all());

        $this->built($this->feed());

        $this->assertSame('', $this->said());
    }

    #[Test]
    public function the_doctor_names_a_failed_build_and_never_the_key(): void
    {
        $feed = $this->feed(['name' => 'Spare parts', 'item' => '']);
        $this->built($feed);

        $this->assertStringContainsString('the last build of «Spare parts» failed', $this->said());
        $this->assertStringNotContainsString($feed->access_key, $this->said());
        $this->assertSame('content', $this->check()[0]->level());
    }

    #[Test]
    public function the_doctor_says_when_a_file_is_two_periods_old(): void
    {
        $feed = $this->feed(['schedule' => 'hourly']);
        $this->built($feed);

        Carbon::setTestNow(Carbon::now()->addMinutes(119));
        $this->assertSame('', $this->said());

        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        $this->assertStringContainsString('older than two periods', $this->said());

        $feed->forceFill(['schedule' => 'manual'])->save();
        $this->assertSame('', $this->said());
    }

    #[Test]
    public function the_doctor_says_when_a_file_is_near_the_limit(): void
    {
        $feed = $this->feed();
        $build = $this->built($feed);

        $this->app['config']->set('webx-catalog-feeds.max_bytes', (int) ($build->bytes / 0.95));

        $this->assertStringContainsString('close to the limit', $this->said());
    }

    #[Test]
    public function the_doctor_says_when_no_worker_takes_the_builds(): void
    {
        $this->built($this->feed());
        $this->app['config']->set('queue.default', 'database');
        $this->app['config']->set('queue.connections.database', ['driver' => 'database', 'table' => 'feed_test_jobs']);

        $schema = $this->app['db']->connection()->getSchemaBuilder();
        $schema->create('feed_test_jobs', static function ($table): void {
            $table->id();
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
        });
        $this->app['db']->connection()->table('feed_test_jobs')->insert(['available_at' => time() - 3600]);

        $found = $this->check();
        $schema->drop('feed_test_jobs');

        $this->assertCount(1, $found);
        $this->assertSame('warn', $found[0]->level());
        $this->assertStringContainsString('no worker', $found[0]->detail);
    }

    #[Test]
    public function the_doctor_reads_robots_as_the_crawlers_do(): void
    {
        $this->built($this->feed());
        $settings = $this->app->make(Settings::class);

        $settings->save(['seo.robots-txt' => "User-agent: *\nDisallow: /cms\n"]);
        $this->assertSame('', $this->said());

        $settings->save(['seo.robots-txt' => "User-agent: *\nDisallow: /feeds/\n"]);
        $this->assertStringContainsString('robots.txt closes /feeds/key.xml to Googlebot', $this->said());
        $this->assertStringContainsString('to AdsBot-Google', $this->said());

        $settings->save(['seo.robots-txt' => "User-agent: *\nDisallow: /\n\nUser-agent: Googlebot\nUser-agent: AdsBot-Google\nAllow: /\n"]);
        $this->assertSame('', $this->said());
    }

    #[Test]
    public function robots_rules_go_by_the_longest_match(): void
    {
        $body = "# a comment\nUser-agent: *\nDisallow: /feeds/\nAllow: /feeds/*.xml$\nDisallow:\n";

        $this->assertTrue(RobotsRules::allows($body, 'Googlebot', '/feeds/key.xml'));
        $this->assertFalse(RobotsRules::allows($body, 'Googlebot', '/feeds/key.csv'));
        $this->assertTrue(RobotsRules::allows('', 'Googlebot', '/feeds/key.xml'));
        $this->assertTrue(RobotsRules::allows("User-agent: Bingbot\nDisallow: /", 'Googlebot', '/feeds/key.xml'));
    }

    #[Test]
    public function the_doctor_has_nothing_to_say_without_a_feed_at_work(): void
    {
        $this->feed(['is_active' => false, 'item' => '']);
        $this->app['config']->set('webx-admin.gate.enabled', true);

        $this->assertSame([], $this->check());
    }

    /** @return list<Diagnosis> */
    private function check(): array
    {
        return $this->app->make(FeedsCheck::class)->run();
    }

    private function said(): string
    {
        return implode("\n", array_map(static fn (Diagnosis $diagnosis): string => $diagnosis->detail, $this->check()));
    }

    private function address(Feed $feed): string
    {
        return '/feeds/'.$feed->refresh()->access_key.'.'.Feed::FORMATS[$feed->format];
    }

    private function built(Feed $feed): FeedBuild
    {
        $build = $this->app->make(FeedBuilder::class)->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $build);

        return $build->refresh();
    }

    private function tickAt(Carbon $now): void
    {
        Carbon::setTestNow($now);

        $this->app->make(FeedWatch::class)->tick();
    }

    private function builds(Feed $feed): int
    {
        return FeedBuild::query()->where('feed_id', $feed->id)->count();
    }

    /** What a worker would have done with a queued build, for a test with the queue faked. */
    private function finish(Feed $feed): void
    {
        FeedBuild::query()->where('feed_id', $feed->id)->where('status', FeedBuild::QUEUED)->update([
            'status' => FeedBuild::DONE,
            'finished_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function finished(Feed $feed, array $values): FeedBuild
    {
        return FeedBuild::query()->create([
            'feed_id' => $feed->id,
            'status' => FeedBuild::DONE,
            'trigger' => FeedBuild::MANUAL,
            'queued_at' => Carbon::now(),
            ...$values,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function importRun(array $values = []): ExchangeRun
    {
        $run = new ExchangeRun;
        $run->forceFill([
            'direction' => ExchangeProfile::IMPORT,
            'format' => 'csv',
            'status' => ExchangeRun::RUNNING,
            ...$values,
        ])->save();

        return $run;
    }

    /** A feed that follows the catalogue, already behind it. */
    private function watched(): Feed
    {
        return $this->feed(['rebuild_on_change' => true, 'built_generation' => 'behind']);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function feed(array $settings = []): Feed
    {
        $feed = new Feed;
        $feed->forceFill([
            'name' => 'Spare parts',
            'format' => 'text',
            'locale' => 'en',
            'currency' => 'EUR',
            'item' => '{{id}}',
            ...$settings,
        ])->save();

        return $feed->refresh();
    }
}
