<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Blocks\Models\Block;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;
use WebxUi\Services\Models\Service;
use WebxUi\Services\ServicesServiceProvider;

/**
 * The demo reviews (§4.9): the rules they are there to show, and the block where a site would put
 * it — a grid with the filter on `/reviews`, a slider of one category in a service.
 */
final class DemoTest extends TestCase
{
    /**
     * With the services: the slider goes into one of them.
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ServicesServiceProvider::class];
    }

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
    public function it_seeds_two_categories_eight_reviews_and_the_block_type(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(2, ReviewCategory::query()->count());
        $this->assertSame(8, Review::query()->count());
        $this->assertSame(1, Review::query()->where('published', false)->count());
        $this->assertSame(1, Review::query()->whereNull('rating')->count());
        $this->assertSame(1, Review::query()->whereNotNull('profile_url')->count());
        $this->assertSame(0, Review::query()->whereNotNull('photo')->count());
        $this->assertTrue(Block::query()->where('slug', 'reviews')->exists());

        $reviews = Review::query()->get();
        $this->assertSame(7, $reviews->filter(static fn (Review $review): bool => $review->visibleIn('en'))->count());
        // The unpublished one and the one without a translation.
        $this->assertSame(6, $reviews->filter(static fn (Review $review): bool => $review->visibleIn('ru'))->count());
    }

    #[Test]
    public function one_review_stands_in_both_categories_in_a_different_place_in_each(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $olga = Review::query()->where('name->en', 'Olga Smirnova')->firstOrFail();
        [$websites, $design] = ReviewCategory::query()->ordered()->get()->all();

        $inWebsites = Review::query()->orderedIn((int) $websites->getKey())->pluck('id')->all();
        $inDesign = Review::query()->orderedIn((int) $design->getKey())->pluck('id')->all();

        $this->assertSame($olga->getKey(), $inWebsites[2]);
        $this->assertSame($olga->getKey(), $inDesign[1]);
    }

    #[Test]
    public function the_page_is_a_grid_with_the_filter_and_the_service_a_slider_of_one_category(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $page = $this->get('/reviews')->assertOk();
        $page->assertSee('data-reviews-layout="grid"', false);
        $page->assertSee('b-reviews__filter', false);
        $page->assertSee('Anna Petrova');
        $page->assertSee('Sofia Kim');
        $page->assertDontSee('Elena Frost');
        // No photos in the demo: the initials stand in.
        $page->assertSee('<span class="b-reviews__initials" aria-hidden="true">AP</span>', false);
        $page->assertSee('rel="nofollow noopener"', false);
        // Decision 3: no markup.
        $page->assertDontSee('"Review"', false);

        $this->nextRequest();

        $service = $this->get('/services/company-website')->assertOk();
        $service->assertSee('Clients about their websites');
        $service->assertSee('data-reviews-layout="slider"', false);
        $service->assertSee('data-reviews-autoplay="on"', false);
        $service->assertSee('Mark Levin');
        $service->assertDontSee('Sofia Kim');
    }

    #[Test]
    public function the_second_language_does_not_see_the_review_it_has_no_text_for(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $page = Page::query()->where('slug->en', 'reviews')->firstOrFail();
        $this->assertSame('reviews', $page->getTranslation('slug', 'ru', false));

        $this->nextRequest('ru');

        $this->get('/ru/reviews')->assertOk()
            ->assertSee('Анна Петрова')
            // A name in one language is shown under it in every language (decision 7).
            ->assertSee('Sofia Kim')
            ->assertDontSee('Давид Коэн')
            ->assertDontSee('David Cohen');
    }

    #[Test]
    public function removing_takes_everything_back_out(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Review::withTrashed()->count());
        $this->assertSame(0, ReviewCategory::withTrashed()->count());
        $this->assertSame(0, Service::withTrashed()->count());
        $this->assertFalse(Page::withTrashed()->where('slug->en', 'reviews')->exists());
        $this->assertFalse(Block::query()->where('slug', 'reviews')->exists());
    }

    /** A fresh request, the way the next page of the site starts with nothing gathered. */
    private function nextRequest(string $locale = 'en'): void
    {
        $this->app->instance('request', Request::create('/'));
        $this->app->make(Locales::class)->use($locale);
    }
}
