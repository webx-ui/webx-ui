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
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

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
        $alex = Member::query()->where('name->en', 'Alex M.')->firstOrFail();
        $slugs = Service::query()->whereKey($alex->relatedIds(Member::SERVICES))->pluck('slug')
            ->map(static fn (mixed $slug): mixed => is_array($slug) ? $slug['en'] : $slug)->sort()->values()->all();
        $this->assertSame(['company-website', 'online-catalogue'], $slugs);
    }

    #[Test]
    public function the_page_is_a_grid_of_everybody_published(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $page = $this->get('/team')->assertOk();
        $page->assertSee('data-team-layout="grid"', false);
        $page->assertSee('Alex M.');
        $page->assertSee('Jordan L.');
        $page->assertDontSee('Max B.');
        // No photos in the demo: the initials stand in.
        $page->assertSee('<span class="b-team__initials" aria-hidden="true">AM</span>', false);
        // Sam's link to a network the config does not have is stored, and left off the card.
        $page->assertSee('https://www.instagram.com/example_sam', false);
        $page->assertDontSee('myspace.com', false);
    }

    #[Test]
    public function the_second_language_shows_everybody_and_a_text_only_where_it_is_written(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->nextRequest('ru');

        $this->get('/ru/team')->assertOk()
            ->assertSee('Робин К.')
            ->assertSee('Алекс М.')
            ->assertSee('Собирает сайты')
            // Decision 8: Robin is on the Russian page without their English text.
            ->assertDontSee('Keeps every project on its calendar');
    }

    #[Test]
    public function the_service_shows_who_does_it(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $service = $this->get('/services/company-website')->assertOk();
        $service->assertSee('Who does it');
        $service->assertSee('data-team-layout="list"', false);
        $service->assertSee('Alex M.');
        $service->assertSee('Sam R.');
        $service->assertDontSee('Kim T.');
    }

    /** A theme's `demo/team/team.json` speaks for the module: the people of the site it is made for. */
    #[Test]
    public function the_theme_brings_a_team_of_its_own(): void
    {
        $theme = sys_get_temp_dir().'/webx-team-demo-theme-'.getmypid();
        @mkdir($theme.'/demo/team', 0777, true);
        file_put_contents($theme.'/demo/team/team.json', (string) json_encode([
            'members' => [['key' => 'lee', 'name' => ['en' => 'Lee N.'], 'job_title' => ['en' => 'Instructor']]],
        ]));

        try {
            $this->app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('acme/theme', $theme, false)]));

            $this->artisan('webx:demo')->assertSuccessful();

            $this->assertSame(['Lee N.'], Member::query()->get()->map(static fn (Member $member): mixed => $member->getTranslation('name', 'en'))->all());
            // The theme's file has no page: the module's own is not mixed in.
            $this->assertFalse(Page::withTrashed()->where('slug->en', 'team')->exists());

            $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();
            $this->assertSame(0, Member::withTrashed()->count());
        } finally {
            @unlink($theme.'/demo/team/team.json');
            @rmdir($theme.'/demo/team');
            @rmdir($theme.'/demo');
            @rmdir($theme);
        }
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
