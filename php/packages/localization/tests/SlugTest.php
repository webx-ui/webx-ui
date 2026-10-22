<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Localization\Slug;

/**
 * The one generator of codes and addresses: a name is transliterated by its own language, the
 * site's default language goes first, and a name of no letters says what it is.
 */
final class SlugTest extends TestCase
{
    #[Test]
    public function a_name_is_transliterated_by_its_own_language(): void
    {
        // Without a language Cyrillic is read the English way: «Хіт» was `xit`.
        $this->assertSame('khit', Slug::make('Хіт', 'uk'));
        $this->assertSame('hit', Slug::make('Хит', 'ru'));
        $this->assertSame('typ-vidkryvannia', Slug::make('Тип відкривання', 'uk'));
        $this->assertSame('pid-rozmir', Slug::make('Під розмір', 'uk'));
    }

    #[Test]
    public function a_name_of_no_letters_takes_the_prefix_when_one_is_given(): void
    {
        $this->assertSame('label-20', Slug::make('−20%', 'uk', prefix: 'label'));
        $this->assertSame('label', Slug::make('%', 'uk', prefix: 'label'));
        $this->assertSame('20', Slug::make('−20%', 'uk'));
        $this->assertSame('', Slug::make('%', 'uk'));
    }

    #[Test]
    public function a_cut_never_leaves_a_dash_at_the_end(): void
    {
        $this->assertSame('pid', Slug::make('Під розмір', 'uk', 4));
    }

    #[Test]
    public function the_default_language_goes_first_whatever_the_order_of_the_map(): void
    {
        $this->assertSame('pid-rozmir', Slug::first(['ru' => 'Под размер', 'uk' => 'Під розмір'], 'uk'));
        $this->assertSame('pod-razmer', Slug::first(['ru' => 'Под размер', 'uk' => ''], 'uk'));
        $this->assertSame('sale', Slug::first(['en' => '   '], 'uk', prefix: 'sale'));
    }
}
