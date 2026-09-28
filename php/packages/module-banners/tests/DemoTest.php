<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;
use WebxUi\Pages\Models\Page;

/**
 * The demo banners (§5.8): a slider's worth in `hero`, one in `promo`, and the rules they are
 * there to show — one off, one without Russian words, no video without a video in the library.
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
    public function it_fills_the_two_declared_places(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $hero = Place::query()->where('key', 'hero')->firstOrFail();
        $promo = Place::query()->where('key', 'promo')->firstOrFail();

        $this->assertSame(4, Banner::query()->where('place_id', $hero->id)->count());
        $this->assertSame(3, Banner::query()->where('place_id', $hero->id)->where('enabled', true)->count());
        $this->assertSame(1, Banner::query()->where('place_id', $promo->id)->count());

        // The demo library has no video, so nothing carries one — the picture stands in.
        $this->assertSame(0, Banner::query()->whereNotNull('video')->count());

        $english = Banner::query()->get()->filter(static fn (Banner $banner): bool => $banner->wordLanguages() === ['en']);
        $this->assertCount(1, $english, 'one banner has no Russian words');

        // "Read more" points at a demo page by entity, so its address comes from the registry.
        $spring = Banner::query()->where('place_id', $hero->id)->orderBy('position')->firstOrFail();
        $link = $spring->buttonRows()[0]['link'];
        $this->assertSame('entity', $link['target']);
        $this->assertTrue(Page::query()->whereKey($link['entity_id'])->whereNotNull('parent_id')->exists());
    }

    #[Test]
    public function the_site_shows_what_the_rules_allow(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $titles = static fn (array $cards): array => array_column($cards, 'title');

        $this->assertSame(['Spring is here', 'Inside the studio', 'Support in English'], $titles(banners('hero')->locale('en')->get()));
        // Decision 12: the English-only banner is not on the Russian pages; the one off is nowhere.
        $this->assertSame(['Пришла весна', 'Внутри студии'], $titles(banners('hero')->locale('ru')->get()));
        $this->assertSame(['Ten percent off in May'], $titles(banners('promo')->locale('en')->get()));
    }

    #[Test]
    public function removing_takes_everything_back_out(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Banner::withTrashed()->count());
        $this->assertSame(0, Place::query()->count());
    }
}
