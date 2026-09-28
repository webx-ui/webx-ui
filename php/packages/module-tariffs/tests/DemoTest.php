<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\Models\Block;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;
use WebxUi\Services\Models\Service;
use WebxUi\Tariffs\Demo\TariffsDemo;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;

/**
 * The demo tariffs (§5.6): the sample the spec was written from, the rules it is there to show,
 * and the block where a site would put it — every tariff in a slider on `/pricing`, "what it
 * costs" in a grid on one service.
 */
final class DemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_one_group_three_tariffs_and_the_block_type(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(1, TariffCategory::query()->count());
        $this->assertSame(3, Tariff::query()->count());
        $this->assertSame(3, TariffCategory::query()->firstOrFail()->tariffs()->count());
        $this->assertSame(1, Tariff::query()->where('featured', true)->count());
        $this->assertTrue(Block::query()->where('slug', 'tariffs')->exists());

        // Growth is linked to the demo service, by the slug the services demo gave it.
        $growth = Tariff::query()->where('name->en', 'Combo Growth')->firstOrFail();
        $slugs = Service::query()->whereKey($growth->relatedIds(Tariff::SERVICES))->pluck('slug')
            ->map(static fn (mixed $slug): mixed => is_array($slug) ? $slug['en'] : $slug)->all();
        $this->assertSame(['company-website'], $slugs);

        // The buttons lead to a page by entity, so their address comes out of the registry.
        $starter = Tariff::query()->where('name->en', 'Combo Starter')->firstOrFail();
        $this->assertSame('entity', $starter->button_link['target'] ?? null);
        $this->assertSame('page', $starter->button_link['entity_type'] ?? null);
    }

    #[Test]
    public function the_page_is_a_slider_of_every_tariff(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $page = $this->get('/pricing')->assertOk();
        $page->assertSee('data-tariffs-layout="slider"', false);
        $page->assertSee('<span class="b-tariffs__amount">$750</span>', false);
        $page->assertSee('<span class="b-tariffs__period">/mo</span>', false);
        $page->assertSee('<p class="b-tariffs__includes">This plan includes:</p>', false);
        $page->assertSee('<p class="b-tariffs__featured">Recommended</p>', false);
        // Enterprise: no number, the words instead.
        $page->assertSee('<span class="b-tariffs__amount">On request</span>', false);
        $page->assertSee('Slack channel with the team');
        $page->assertSee('b-tariffs__button--secondary', false);
    }

    #[Test]
    public function the_second_language_has_a_line_less_in_starter(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->nextRequest('ru');

        $this->get('/ru/pricing')->assertOk()
            ->assertSee('Дизайн до пяти страниц')
            ->assertSee('По запросу')
            ->assertSee('/мес')
            ->assertSee('В тариф входит:')
            // Decision 12: the line written only in English drops out of the Russian list.
            ->assertDontSee('Slack channel with the team');
    }

    #[Test]
    public function the_service_shows_what_it_costs(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $service = $this->get('/services/company-website')->assertOk();
        $service->assertSee('What it costs');
        $service->assertSee('data-tariffs-layout="grid"', false);
        $service->assertSee('<h3 class="b-tariffs__name">Combo Growth</h3>', false);
        $service->assertDontSee('<h3 class="b-tariffs__name">Combo Starter</h3>', false);
    }

    #[Test]
    public function without_the_pages_demo_the_buttons_lead_to_the_first_page_of_the_registry(): void
    {
        // A live site: the pages were there before the demo, so the pages demo seeds nothing.
        $about = $this->page('about-us');

        // Straight to the module, past the command: nothing else is seeded, the journal is empty.
        $this->app->make(TariffsDemo::class)->seed($this->app->make(DemoLedger::class));

        $starter = Tariff::query()->where('name->en', 'Combo Starter')->firstOrFail();
        $first = Route::query()->where('kind', Route::CANONICAL)->where('entity_type', (new Page)->getMorphClass())
            ->where('path', '!=', '')->orderBy('id')->value('entity_id');

        $this->assertSame($about->id, (int) $first);
        $this->assertSame($about->id, $starter->button_link['entity_id'] ?? null);
    }

    #[Test]
    public function removing_takes_everything_back_out(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Tariff::withTrashed()->count());
        $this->assertSame(0, TariffCategory::withTrashed()->count());
        $this->assertSame(0, Service::withTrashed()->count());
        $this->assertFalse(Page::withTrashed()->where('slug->en', 'pricing')->exists());
        $this->assertFalse(Block::query()->where('slug', 'tariffs')->exists());
    }

    /** A fresh request, the way the next page of the site starts with nothing gathered. */
    private function nextRequest(string $locale = 'en'): void
    {
        $this->app->instance('request', Request::create('/'));
        $this->app->make(Locales::class)->use($locale);
    }
}
