<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;
use WebxUi\Press\Models\Outlet;

/**
 * An outlet's address out of its name, when nobody wrote one, is transliterated by the name's
 * language, the site's main language first: «Хіт» is `khit` in Ukrainian — never `xit`.
 */
final class SlugLanguageTest extends TestCase
{
    #[Test]
    public function an_outlet_without_a_slug_takes_one_from_the_name_in_the_sites_main_language(): void
    {
        Locale::fromCode('uk', ['is_default' => true, 'sort' => 0])->save();
        Locale::fromCode('ru', ['sort' => 1])->save();
        $this->app->make(Locales::class)->forget();

        $outlet = new Outlet(['title' => ['ru' => 'Хит', 'uk' => 'Хіт']]);
        $outlet->published = true;
        $outlet->save();
        $outlet->articles()->create(['title' => ['uk' => 'Інтерв’ю', 'ru' => 'Интервью'], 'url' => 'https://news.example/a', 'published_on' => '2025-03-14']);

        $outlet->refresh()->fillSlugs();
        $this->assertSame(['uk' => 'khit', 'ru' => 'khit'], $outlet->getTranslations('slug'));
    }
}
