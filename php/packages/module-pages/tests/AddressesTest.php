<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\Models\Route;

class AddressesTest extends TestCase
{
    #[Test]
    public function a_taken_address_is_refused_rather_than_suffixed(): void
    {
        $this->page('about');

        $this->expectException(PathRejected::class);

        $this->page('about');
    }

    #[Test]
    public function a_refused_address_leaves_no_page_behind(): void
    {
        $this->page('about');

        try {
            $this->page('about');
        } catch (PathRejected $rejected) {
            $this->assertSame('slug', $rejected->attribute, 'the error belongs under the slug field');
        }

        $this->assertSame(1, Page::query()->where('lft', '>', 0)->whereNotNull('parent_id')->count());
    }

    #[Test]
    public function two_pages_may_share_a_slug_under_different_parents(): void
    {
        $shoes = $this->page('shoes', $this->page('catalog'));
        $news = $this->page('shoes', $this->page('news'));

        $this->assertSame('catalog/shoes', $shoes->routePath());
        $this->assertSame('news/shoes', $news->routePath());
    }

    #[Test]
    public function a_slug_in_a_second_language_is_a_second_row_in_the_registry(): void
    {
        $this->useLocales('en', 'uk');

        $about = $this->page('about');
        $about->setTranslation('slug', 'uk', 'pro-nas');
        $about->save();

        $this->assertSame('about', $about->routePath('en'));
        $this->assertSame('pro-nas', $about->routePath('uk'));

        $this->assertSame(
            ['about', 'pro-nas'],
            Route::query()->forEntity($about)->orderBy('locale')->pluck('path')->all(),
        );
    }

    #[Test]
    public function a_language_without_a_slug_has_no_address_at_all(): void
    {
        $this->useLocales('en', 'uk');

        $about = $this->page('about');

        // `about` in English, nothing in Ukrainian: the page does not open there, which is
        // honester than serving one language's address for another language's content (§8).
        $this->assertSame(
            ['en'],
            Route::query()->forEntity($about)->pluck('locale')->all(),
        );
    }

    #[Test]
    public function an_address_the_application_already_answers_is_refused(): void
    {
        $this->app['router']->get('/account', static fn (): string => 'mine');

        $this->expectException(PathRejected::class);

        $this->page('account');
    }
}
