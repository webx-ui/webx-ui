<?php

declare(strict_types=1);

namespace WebxUi\Team\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\Models\Block;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Services\Models\Service;
use WebxUi\Team\Models\Member;

/**
 * The demo team (§5.9): the rules it is there to show, and the block where a site would put it —
 * everybody in a grid on `/team`, "who does it" in a list on one service.
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
    public function it_seeds_six_people_and_the_block_type(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(6, Member::query()->count());
        $this->assertSame(1, Member::query()->where('published', false)->count());
        $this->assertSame(3, Member::query()->whereNotNull('socials')->count());
        $this->assertSame(0, Member::query()->whereNotNull('photo')->count());
        $this->assertTrue(Block::query()->where('slug', 'team')->exists());

        // The people are linked to the demo services, by the slugs the services demo gave them.
        $anna = Member::query()->where('name->en', 'Anna Petrova')->firstOrFail();
        $slugs = Service::query()->whereKey($anna->relatedIds(Member::SERVICES))->pluck('slug')
            ->map(static fn (mixed $slug): mixed => is_array($slug) ? $slug['en'] : $slug)->sort()->values()->all();
        $this->assertSame(['company-website', 'online-catalogue'], $slugs);
    }

    #[Test]
    public function the_page_is_a_grid_of_everybody_published(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $page = $this->get('/team')->assertOk();
        $page->assertSee('data-team-layout="grid"', false);
        $page->assertSee('Anna Petrova');
        $page->assertSee('Maria Volkova');
        $page->assertDontSee('Dmitry Sokolov');
        // No photos in the demo: the initials stand in.
        $page->assertSee('<span class="b-team__initials" aria-hidden="true">AP</span>', false);
        // Igor's link to a network the config does not have is stored, and left off the card.
        $page->assertSee('https://www.instagram.com/example_igor', false);
        $page->assertDontSee('myspace.com', false);
    }

    #[Test]
    public function the_second_language_shows_everybody_and_a_text_only_where_it_is_written(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->nextRequest('ru');

        $this->get('/ru/team')->assertOk()
            ->assertSee('Ольга Кузнецова')
            ->assertSee('Анна Петрова')
            ->assertSee('Собирает сайты')
            // Decision 8: Olga is on the Russian page without her English text.
            ->assertDontSee('Keeps every project on its calendar');
    }

    #[Test]
    public function the_service_shows_who_does_it(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $service = $this->get('/services/company-website')->assertOk();
        $service->assertSee('Who does it');
        $service->assertSee('data-team-layout="list"', false);
        $service->assertSee('Anna Petrova');
        $service->assertSee('Igor Smirnov');
        $service->assertDontSee('Pavel Orlov');
    }

    #[Test]
    public function removing_takes_everything_back_out(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Member::withTrashed()->count());
        $this->assertSame(0, Service::withTrashed()->count());
        $this->assertFalse(Page::withTrashed()->where('slug->en', 'team')->exists());
        $this->assertFalse(Block::query()->where('slug', 'team')->exists());
    }

    /** A fresh request, the way the next page of the site starts with nothing gathered. */
    private function nextRequest(string $locale = 'en'): void
    {
        $this->app->instance('request', Request::create('/'));
        $this->app->make(Locales::class)->use($locale);
    }
}
