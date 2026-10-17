<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Cache\CacheGroups;
use WebxUi\Pages\Models\Page;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Rendering\OutletLoader;

/**
 * The query budget of every public page of the press — an outlet's page, the page of blocks at
 * the prefix and each block type on its own — and the freshness of what they cache
 * (WEBX_UI_PERFORMANCE_AUDIT.md §7).
 *
 * Every budget is a ceiling the page fits under today with thirty outlets, not a target, and the
 * point of each is that it does not grow with the rows: a block that asks the registry once per
 * card is caught by `assertNoRepeatedQueries()` on the warm pass whatever the count. A change
 * that needs more says why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    private const OUTLETS = 30;

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
     * The three blocks of the demo's page: the outlets are loaded once for all of them. The
     * library is asked twice — the logos with the outlets, the PDFs with the articles — which
     * is two lists, not a row-by-row loop; the blocks on their own are the no-repeat check.
     */
    #[Test]
    public function the_page_of_blocks_fits_its_budget_cold_and_warm(): void
    {
        $this->seedPress();

        $this->cold();
        $this->assertQueriesWithin(24, fn () => $this->get('/press')->assertOk());

        $this->assertQueriesWithin(14, fn () => $this->get('/press')->assertOk());
    }

    #[Test]
    public function an_outlet_fits_its_budget_cold_and_warm(): void
    {
        $this->seedPress();

        $this->cold();
        $this->assertQueriesWithin(18, fn () => $this->get('/press/outlet-1')->assertOk());

        $this->assertQueriesWithin(12, fn () => $this->get('/press/outlet-1')->assertOk());
    }

    /**
     * Each block type on a page of its own: the outlets loaded once and narrowed in PHP, the
     * feed one query over them. A new request each time, so the loader of the one before does
     * not stand in for the budget.
     */
    #[Test]
    public function each_block_fits_its_budget(): void
    {
        $this->seedPress();

        foreach ($this->blocks() as $name => $block) {
            $this->cold();
            $this->newRequest();
            $this->assertQueriesWithin(10, fn () => $this->render($block), "{$name} cold");

            $this->newRequest();
            $this->assertQueriesWithin(6, fn () => $this->render($block), "{$name} warm");
            $this->newRequest();
            $this->assertNoRepeatedQueries(fn () => $this->render($block), "{$name} warm");
        }
    }

    #[Test]
    public function the_sitemap_fits_its_budget(): void
    {
        $this->seedPress();

        // The build: the outlets with their cards in bulk, the pages at the prefix alongside.
        $this->cold();
        $this->assertQueriesWithin(20, fn () => $this->get('/sitemap.xml')->assertOk());
        // Served from the cache: nothing but the request's own infrastructure.
        $this->assertQueriesWithin(4, fn () => $this->get('/sitemap-press-outlet.xml')->assertOk());
    }

    /**
     * A save through the panel moves the `press` group the loaded outlets are kept under, and
     * the next request — in the same process, the way a worker serves it — reads the new rows:
     * the blocks at the prefix, the page of the outlet, and the old address redirecting to the
     * new one.
     */
    #[Test]
    public function a_page_shows_a_change_made_through_the_panel_at_once(): void
    {
        $this->seedPress();
        $editor = $this->editor();
        $newest = 'outlet-'.self::OUTLETS;
        $title = 'Outlet '.self::OUTLETS;

        $this->get('/press')->assertOk()->assertSee($title);
        $this->get('/press/'.$newest)->assertOk()->assertSee($title);

        $outlet = Outlet::query()->where('slug->en', $newest)->firstOrFail();
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($outlet->getKey()), ['values' => ['title' => ['en' => 'The newest, renamed'], 'slug' => ['en' => 'the-newest']]])
            ->assertOk();

        $this->get('/press')->assertOk()->assertSee('The newest, renamed')->assertDontSee('>'.$title.'<', false);
        $this->get('/press/the-newest')->assertOk()->assertSee('The newest, renamed');
        $this->get('/press/'.$newest)->assertRedirect('/press/the-newest');
    }

    /**
     * A reorder writes positions without model events: the group is bumped by hand, and the
     * strip of logos — rendered in the same process — follows the new order at once.
     */
    #[Test]
    public function a_reorder_through_the_panel_shows_at_once(): void
    {
        $this->seedPress();
        $editor = $this->editor();

        // Both in the strip of logos, which is the first block of the page.
        $page = (string) $this->get('/press')->assertOk()->getContent();
        $this->assertLessThan(strpos($page, 'alt="Outlet 6"'), strpos($page, 'alt="Outlet 3"'));

        $ids = Outlet::query()->orderByDesc('id')->pluck('id')->map(intval(...))->all();
        $this->actingAs($editor, 'cms')->postJson($this->api('reorder'), ['ids' => $ids])->assertNoContent();

        $page = (string) $this->get('/press')->assertOk()->getContent();
        $this->assertLessThan(strpos($page, 'alt="Outlet 3"'), strpos($page, 'alt="Outlet 6"'));
    }

    /**
     * Thirty published outlets with a logo and two articles each — the kinds in turn, every
     * third outlet in the strip of logos, every fourth article with a PDF — plus one unpublished,
     * and the page at the prefix with the three block types on it, the way the demo lays it out.
     */
    private function seedPress(): void
    {
        // Frozen, so that two runs print the same `lastmod` and the same dates.
        Carbon::setTestNow('2026-10-17 10:00:00');

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['press']])->assertSuccessful();

        $logo = $this->file('media/ab/cd/logo.png', 'image/png');
        $scan = $this->file();
        $kinds = ['mention', 'interview', 'expert_comment', 'authored'];

        for ($i = 1; $i <= self::OUTLETS; $i++) {
            $rows = [];

            foreach ([0, 1] as $n) {
                $day = sprintf('2024-%02d-%02d', ($i % 12) + 1, ($n * 10) + 1);
                $rows[] = $this->row("Article {$i}-{$n}", [
                    'kind' => $kinds[($i + $n) % 4],
                    'published_on' => $day,
                    'excerpt' => ['en' => "A few words about article {$i}-{$n}."],
                    ...(($i + $n) % 4 === 0 ? ['file' => ['path' => $scan->path]] : []),
                ]);
            }

            $this->outlet("Outlet {$i}", $rows, values: [
                'summary' => ['en' => "What outlet {$i} is."],
                'website_url' => "https://outlet-{$i}.example",
                'featured' => $i % 3 === 0,
                'logo' => ['path' => $logo->path],
            ]);
        }

        $this->outlet('Unpublished', [$this->row('Unseen')], published: false);

        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);
        $page = new Page([
            'title' => 'Press',
            'slug' => 'press',
            'blocks' => array_values($this->blocks()),
        ]);
        $page->appendTo($home);
        $page->publish();
    }

    /**
     * The three block types with the values the demo gives them.
     *
     * @return array<string, array<string, mixed>>
     */
    private function blocks(): array
    {
        return [
            'logos' => ['key' => 'press-logos', 'type' => 'press-logos', 'values' => ['title' => ['en' => 'As featured in'], 'featured' => true]],
            'outlets' => ['key' => 'press-outlets', 'type' => 'press-outlets', 'values' => ['title' => ['en' => 'Press'], 'group' => true, 'kinds' => []]],
            'articles' => ['key' => 'press-articles', 'type' => 'press-articles', 'values' => ['title' => ['en' => 'In the press'], 'kinds' => [], 'limit' => 6]],
        ];
    }

    /** @param  array<string, mixed>  $block */
    private function render(array $block): string
    {
        return (string) $this->app->make(Renderer::class)->render([$block]);
    }

    /** The first visitor after a deploy: an empty store, and nothing read in this process either. */
    private function cold(): void
    {
        Cache::flush();
        $this->app->make(CacheGroups::class)->forgetMemo();
    }

    /** A block rendered outside a request: the next one gets a loader of its own, as a request would. */
    private function newRequest(): void
    {
        $this->app->forgetInstance(OutletLoader::class);
    }
}
