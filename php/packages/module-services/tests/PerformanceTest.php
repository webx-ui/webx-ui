<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cache\CacheGroups;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * The query budget of every public page of the services, and the freshness of what they cache
 * (WEBX_UI_PERFORMANCE_AUDIT.md §7).
 *
 * Every budget is a ceiling the page fits under today with thirty-three services, not a target,
 * and the point of each is that it does not grow with the rows: a page that asks the registry
 * once per card is caught by `assertNoRepeatedQueries()` on the warm pass whatever the count. A
 * change that needs more says why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    private const SERVICES = 30;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The caches on, the way a site has them: the budget of a warm page is the point.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.cache.enabled', true);
        $app['config']->set('webx-seo.cache.enabled', true);
    }

    /**
     * The categories with their services, and the services of no visible category after them:
     * two lists, each with its covers and its addresses in one query.
     */
    #[Test]
    public function the_index_fits_its_budget_cold_and_warm(): void
    {
        $this->seedServices();

        $this->cold();
        $this->assertQueriesWithin(16, fn () => $this->get('/services')->assertOk());

        $this->assertQueriesWithin(10, fn () => $this->get('/services')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/services')->assertOk());
    }

    #[Test]
    public function a_category_fits_its_budget_cold_and_warm(): void
    {
        $this->seedServices();

        $this->cold();
        $this->assertQueriesWithin(15, fn () => $this->get('/services/consulting')->assertOk());

        $this->assertQueriesWithin(8, fn () => $this->get('/services/consulting')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/services/consulting')->assertOk());
    }

    #[Test]
    public function a_service_fits_its_budget_cold_and_warm(): void
    {
        $this->seedServices();

        $this->cold();
        $this->assertQueriesWithin(15, fn () => $this->get('/services/service-1')->assertOk());

        $this->assertQueriesWithin(9, fn () => $this->get('/services/service-1')->assertOk());
    }

    #[Test]
    public function the_sitemap_fits_its_budget(): void
    {
        $this->seedServices();

        // The build: both types of the module, chunked, with their covers in bulk.
        $this->cold();
        $this->assertQueriesWithin(16, fn () => $this->get('/sitemap.xml')->assertOk());
        // Served from the cache: nothing but the request's own infrastructure.
        $this->assertQueriesWithin(4, fn () => $this->get('/sitemap-service.xml')->assertOk());
        $this->assertQueriesWithin(4, fn () => $this->get('/sitemap-service-category.xml')->assertOk());
    }

    /**
     * A save through the panel moves the groups a page is cached on, and the next request — in
     * the same process, the way a worker serves it — reads the new rows: the index, the page of
     * the service, and the old address redirecting to the new one.
     */
    #[Test]
    public function a_page_shows_a_change_made_through_the_panel_at_once(): void
    {
        $this->seedServices();
        $editor = $this->editor();
        $newest = 'service-'.self::SERVICES;
        $title = 'Service '.self::SERVICES;

        $this->get('/services')->assertOk()->assertSee($title);
        $this->get('/services/'.$newest)->assertOk()->assertSee($title);

        $service = Service::query()->where('slug->en', $newest)->firstOrFail();
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($service->getKey()), ['values' => ['title' => ['en' => 'The newest, renamed'], 'slug' => ['en' => 'the-newest']]])
            ->assertOk();
        $this->actingAs($editor, 'cms')->postJson($this->api($service->getKey().'/publish'))->assertOk();

        $this->get('/services')->assertOk()->assertSee('The newest, renamed')->assertDontSee('>'.$title.'<', false);
        $this->get('/services/the-newest')->assertOk()->assertSee('The newest, renamed');
        $this->get('/services/'.$newest)->assertRedirect('/services/the-newest');
    }

    /**
     * Three visible categories and a hidden one, thirty published services with a cover and a
     * category each — every fifth in two — and three more, with the second picture of the
     * library, filed only under the hidden category, which the index lists as "other".
     */
    private function seedServices(): void
    {
        // Frozen, so that two runs print the same `lastmod` and the same dates.
        Carbon::setTestNow('2026-10-17 10:00:00');

        $categories = array_map(fn (string $slug): ServiceCategory => $this->category($slug), ['consulting', 'repair', 'design']);
        $hidden = $this->category('archive', visible: false);
        $wide = $this->picture('cover');
        $square = $this->picture('square');

        for ($i = 1; $i <= self::SERVICES; $i++) {
            $ids = [$categories[$i % 3]->getKey()];

            if ($i % 5 === 0) {
                $ids[] = $categories[($i + 1) % 3]->getKey();
            }

            $this->publishedService($i, $wide, $ids);
        }

        for ($i = self::SERVICES + 1; $i <= self::SERVICES + 3; $i++) {
            $this->publishedService($i, $square, [$hidden->getKey()]);
        }
    }

    /** @param  list<int>  $categories */
    private function publishedService(int $i, MediaFile $cover, array $categories): Service
    {
        $service = new Service([
            'title' => "Service {$i}",
            'slug' => "service-{$i}",
            'lead' => "The lead of service {$i}.",
            'cover_id' => $cover->getKey(),
        ]);
        $service->save();
        $service->syncCategories($categories);
        $service->publish();

        return $service;
    }

    private function picture(string $name): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => "media/ab/cd/{$name}.jpg",
            'hash' => md5($name),
            'name' => $name,
            'file_name' => "{$name}.jpg",
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
        ]);
    }

    /** The first visitor after a deploy: an empty store, and nothing read in this process either. */
    private function cold(): void
    {
        Cache::flush();
        $this->app->make(CacheGroups::class)->forgetMemo();
    }
}
