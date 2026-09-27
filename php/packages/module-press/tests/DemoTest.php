<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\Models\Block;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Pages\Models\Page;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;

/**
 * The demo press (§4.12): the rules it is there to show, and the page a site puts it on — the strip
 * of the marked logos over the catalogue in groups.
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
    public function it_seeds_four_outlets_eight_articles_and_the_files_they_need(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(4, Outlet::query()->count());
        $this->assertSame(8, Article::query()->count());
        $this->assertSame(1, Outlet::query()->where('published', false)->count());
        $this->assertSame(1, Outlet::query()->where('published', true)->where('featured', false)->count());

        // Every kind of the default four, and every rule of decisions 7–10 once at least.
        $this->assertEqualsCanonicalizing(
            ['mention', 'interview', 'expert_comment', 'authored'],
            Article::query()->distinct()->pluck('kind')->all(),
        );
        $this->assertSame(1, Article::query()->where('is_hidden', true)->count());
        $this->assertSame(2, Article::query()->where('date_precision', 'month')->count());
        $this->assertSame(1, Article::query()->where('date_precision', 'year')->count());
        $this->assertSame(1, Article::query()->whereNull('url')->whereNotNull('file')->count());
        $this->assertSame(1, Article::query()->whereNotNull('url')->whereNotNull('file')->count());

        $articles = Article::query()->get();
        $this->assertSame(7, $articles->filter(static fn (Article $article): bool => $article->visibleIn('en'))->count());
        // The hidden one and the one in English only.
        $this->assertSame(6, $articles->filter(static fn (Article $article): bool => $article->visibleIn('ru'))->count());

        // The logos and the PDF, in a folder of their own, through the library's own door.
        $folder = MediaDirectory::query()->where('title', 'Press')->firstOrFail();
        $this->assertSame(5, MediaFile::query()->where('directory_id', $folder->getKey())->count());
        $this->assertSame(1, MediaFile::query()->where('mime', 'application/pdf')->count());
        Storage::disk('public')->assertExists((string) Outlet::query()->firstOrFail()->logoPath());

        $this->assertSame(3, Block::query()->where('slug', 'like', 'press-%')->count());
    }

    #[Test]
    public function the_page_at_the_prefix_is_the_strip_over_the_catalogue_in_groups(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $page = Page::query()->where('slug->en', 'press')->firstOrFail();
        $this->assertSame(['press-logos', 'press-outlets'], array_column($page->blocksTree(), 'type'));

        $response = $this->get('/press')->assertOk();

        $response->assertSee('As featured in');
        $response->assertSee('b-press-logos', false);
        $response->assertSee('b-press-outlets', false);
        // The unpublished outlet is on neither.
        $response->assertDontSee('Kitchen Weekly');
        $response->assertSee('City Portal');

        $this->nextRequest();

        $this->get('/press/health-and-style')->assertOk()
            ->assertSee('Ten kitchens worth a visit')
            ->assertSee('March 2025')
            ->assertDontSee('An old note');
    }

    #[Test]
    public function the_second_language_does_not_see_the_article_it_has_no_title_for(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->nextRequest('ru');

        $this->get('/ru/press/health-and-style')->assertOk()
            ->assertSee('Интервью: как собрать тарелку на неделю')
            // A month on its own is named, not declined (§4.5).
            ->assertSee('март 2025')
            ->assertDontSee('Ten kitchens worth a visit');
    }

    #[Test]
    public function removing_takes_everything_back_out(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Outlet::withTrashed()->count());
        $this->assertSame(0, Article::query()->count());
        $this->assertSame(0, MediaFile::query()->count());
        $this->assertFalse(MediaDirectory::query()->where('title', 'Press')->exists());
        $this->assertFalse(Page::withTrashed()->where('slug->en', 'press')->exists());
        $this->assertFalse(Block::query()->where('slug', 'like', 'press-%')->exists());
    }

    /** A fresh request, the way the next page of the site starts with nothing gathered. */
    private function nextRequest(string $locale = 'en'): void
    {
        $this->app->instance('request', Request::create('/'));
        $this->app->make(Locales::class)->use($locale);
    }
}
