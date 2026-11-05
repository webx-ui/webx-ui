<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Formats\FeedFormats;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogFeeds\Tests\Fixtures\LinesFormat;

/**
 * A file written by a PHP class (§10 of the feeds spec): the same selection, third layer, jobs,
 * journal and delivery as a template, and nothing a form sends can make a feed one.
 */
final class FormatClassTest extends TestCase
{
    use CountsQueries;

    private const API = '/api/cms/catalog/feeds';

    private Shop $shop;

    private LinesFormat $format;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::seedOnce($this->app, self::class);
        Storage::fake('local');

        $this->format = new LinesFormat;
        $this->app->make(FeedFormats::class)->register($this->format);
    }

    #[Test]
    public function a_class_writes_the_file_a_template_would_select_for(): void
    {
        $filter = ['categories' => ['include' => [$this->shop->category('Knives')->id]]];
        $template = $this->build($this->feed(['driver' => null, 'filter' => $filter, 'item' => '{{sku}}']));
        $build = $this->build($this->feed(['filter' => $filter]));

        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);
        $this->assertGreaterThan(0, $build->written);
        $this->assertSame($template->written, $build->written);
        $this->assertSame($template->selected, $build->selected);
        $this->assertSame($template->rejected, $build->rejected);

        $file = json_decode($this->file($build), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($file);
        $this->assertSame('Spare parts', $file['shop']);
        $this->assertSame($build->written, $file['count']);
        $this->assertSame(explode("\n", $this->file($template)), array_column($file['items'], 0));
        $this->assertStringEndsWith('.lines', (string) $build->path);
    }

    #[Test]
    public function a_class_that_keeps_nothing_gets_through_a_build_of_many_jobs(): void
    {
        Queue::fake();
        $this->app['config']->set('webx-catalog-feeds.chunk', 1);

        $build = $this->app->make(FeedBuilder::class)->start($this->feed());
        $this->assertInstanceOf(FeedBuild::class, $build);

        $jobs = 0;

        // Each stretch is a builder of its own, as a job on a worker gets, and has no time for a second page.
        while ($this->app->make(FeedBuilder::class)->work((int) $build->id, 0.0)) {
            $jobs++;
        }

        $build->refresh();

        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);
        $this->assertGreaterThan(3, $jobs);

        $file = json_decode($this->file($build), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($file);
        $this->assertCount($build->written, $file['items']);
    }

    #[Test]
    public function the_file_is_served_by_what_the_class_says(): void
    {
        $feed = $this->feed();
        $build = $this->build($feed);

        $this->assertStringEndsWith('.lines', $feed->address());

        $answer = $this->get($this->path($feed, 'lines'))->assertOk();

        $this->assertSame('application/x-lines', $answer->headers->get('Content-Type'));
        $this->assertSame($this->file($build), $answer->streamedContent());

        $this->get($this->path($feed, 'lines'), ['If-None-Match' => (string) $answer->headers->get('ETag')])->assertStatus(304);
        $this->get($this->path($feed, 'txt'))->assertRedirect($feed->address());
    }

    #[Test]
    public function a_file_the_class_refuses_replaces_nothing(): void
    {
        $feed = $this->feed();
        $good = $this->build($feed);

        $this->format->refusal = 'Line 3 has no price.';
        $bad = $this->build($feed);

        $this->assertSame(FeedBuild::FAILED, $bad->status);
        $this->assertStringContainsString('Line 3 has no price.', (string) $bad->error);
        $this->assertSame($good->id, $feed->refresh()->current_build_id);
        $this->get($this->path($feed, 'lines'))->assertOk();
    }

    #[Test]
    public function a_class_that_is_gone_fails_the_build_and_the_last_file_stays_where_it_was(): void
    {
        $feed = $this->feed();
        $good = $this->build($feed);

        $this->app->forgetInstance(FeedFormats::class);

        $failed = $this->build($feed);

        $this->assertSame(FeedBuild::FAILED, $failed->status);
        $this->assertStringContainsString('[lines]', (string) $failed->error);
        $this->assertSame($good->id, $feed->refresh()->current_build_id);
        $this->assertStringEndsWith('.lines', $feed->address());

        $answer = $this->get($this->path($feed, 'lines'))->assertOk();

        $this->assertSame('application/x-lines', $answer->headers->get('Content-Type'));
        $this->assertSame($this->file($good), $answer->streamedContent());
    }

    #[Test]
    public function a_name_the_site_does_not_have_is_a_failure_and_not_a_hole_in_the_file(): void
    {
        $this->format->names = ['sku', 'no_such_variable'];

        $build = $this->build($this->feed());

        $this->assertSame(FeedBuild::FAILED, $build->status);
        $this->assertStringContainsString('no_such_variable', (string) $build->error);
    }

    #[Test]
    public function the_settings_of_a_feed_reach_the_values_of_a_class(): void
    {
        $this->format->names = ['sku', 'custom_label_0'];

        $build = $this->build($this->feed(['custom_labels' => ['0' => ['type' => 'constant', 'value' => 'Spring']]]));

        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);

        $file = json_decode($this->file($build), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($file);
        $this->assertSame(['Spring'], array_values(array_unique(array_column($file['items'], 1))));
    }

    #[Test]
    public function a_format_is_offered_as_a_preset_and_described_for_the_form(): void
    {
        $this->actingAs($this->editor(), 'cms');

        $meta = $this->getJson(self::API.'/meta')->assertOk();

        $this->assertSame([[
            'key' => 'lines',
            'label' => 'Lines of a marketplace',
            'class' => LinesFormat::class,
            'extension' => 'lines',
            'content_type' => 'application/x-lines',
        ]], $meta->json('data.drivers'));

        $preset = array_column((array) $meta->json('data.presets'), null, 'key')['lines'];

        $this->assertSame('Lines of a marketplace', $preset['name']);
        $this->assertSame('lines', $preset['values']['driver']);
    }

    #[Test]
    public function a_feed_gets_its_class_from_the_preset_and_needs_no_template(): void
    {
        $this->actingAs($this->editor(), 'cms');

        $id = $this->postJson(self::API, ['preset' => 'lines', 'name' => 'For a marketplace'])
            ->assertCreated()
            ->assertJsonPath('data.driver', 'lines')
            ->json('data.id');

        $feed = Feed::query()->findOrFail($id);

        $this->assertSame('lines', $feed->driver);
        $this->assertSame(FeedBuild::DONE, $this->build($feed)->status);
    }

    #[Test]
    public function nothing_a_form_sends_makes_a_feed_a_class(): void
    {
        $this->actingAs($this->editor(), 'cms');

        $id = $this->postJson(self::API, ['name' => 'Mine', 'format' => 'text', 'item' => '{{sku}}', 'driver' => 'lines'])
            ->assertCreated()
            ->assertJsonPath('data.driver', null)
            ->json('data.id');

        $this->putJson(self::API.'/'.$id, ['driver' => 'lines', 'preset' => 'lines'])
            ->assertOk()
            ->assertJsonPath('data.driver', null);

        $this->postJson(self::API, ['preset' => 'csv', 'driver' => 'lines'])
            ->assertCreated()
            ->assertJsonPath('data.driver', null);

        $feed = $this->feed();

        $this->putJson(self::API.'/'.$feed->id, ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.driver', 'lines');
    }

    #[Test]
    public function the_list_reads_no_build_per_feed_whether_the_class_is_there_or_gone(): void
    {
        $gone = $this->feedsOfBothKinds();

        $this->actingAs($this->editor(), 'cms');
        $this->getJson(self::API)->assertOk();

        $answer = $this->assertNoRepeatedQueries(fn () => $this->getJson(self::API)->assertOk());

        $addresses = array_column((array) $answer->json('data'), 'address', 'id');

        $this->assertCount(4, $addresses);

        foreach ($gone as $feed) {
            $this->assertStringEndsWith('.lines', (string) $addresses[$feed->id]);
        }
    }

    #[Test]
    public function a_file_reads_its_build_once(): void
    {
        $gone = $this->feedsOfBothKinds()[0];
        $there = Feed::query()->where('driver', 'lines')->firstOrFail();

        foreach ([$there, $gone] as $feed) {
            $path = $this->path($feed, 'lines');
            $tally = $this->recordQueries(fn () => $this->get($path)->assertOk());

            $this->assertCount(1, array_filter(
                $tally->queries,
                static fn (array $query): bool => str_contains($query['query'], 'catalog_feed_builds'),
            ), (string) $feed->driver.PHP_EOL.$tally->describe());
        }
    }

    #[Test]
    public function a_mistake_in_a_registration_stops_the_boot(): void
    {
        $formats = $this->app->make(FeedFormats::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already registered');

        $formats->register(new LinesFormat);
    }

    /**
     * Two feeds whose class is there and two whose class is gone, each with a file: one of a kind
     * would read its build once and look like no loop at all.
     *
     * @return list<Feed> the two whose class is gone
     */
    private function feedsOfBothKinds(): array
    {
        $gone = [];

        foreach (range(1, 4) as $number) {
            $feed = $this->feed(['name' => 'Feed '.$number]);
            $this->build($feed);

            if ($number > 2) {
                $feed->forceFill(['driver' => 'gone'])->save();
                $gone[] = $feed;
            }
        }

        return $gone;
    }

    private function build(Feed $feed): FeedBuild
    {
        $build = $this->app->make(FeedBuilder::class)->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $build);

        return $build->refresh();
    }

    private function file(FeedBuild $build): string
    {
        return (string) Storage::disk('local')->get((string) $build->path);
    }

    private function path(Feed $feed, string $extension): string
    {
        return '/feeds/'.$feed->refresh()->access_key.'.'.$extension;
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
            'driver' => 'lines',
            'locale' => 'en',
            'currency' => 'EUR',
            ...$settings,
        ])->save();

        return $feed->refresh();
    }
}
