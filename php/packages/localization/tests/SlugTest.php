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
    public function a_slug_in_another_alphabet_is_brought_to_ascii_and_a_latin_one_is_left_alone(): void
    {
        $this->assertSame('tormoznye-kolodki', Slug::latin('тормозные-колодки', 'ru'));
        $this->assertSame('khit', Slug::latin('Хіт', 'uk'));
        // `_` means something in a slug, and `make()` would have made it a hyphen.
        $this->assertSame('tormoza_zadnie', Slug::latin('тормоза_задние', 'ru'));
        // Latin of a wrong shape is the form's to refuse, not this method's to repair.
        $this->assertSame('Brake Pads', Slug::latin('Brake Pads', 'ru'));
        $this->assertSame('', Slug::latin('漢字', 'ru'));
        $this->assertSame('', Slug::latin('!!!', 'ru'));
        $this->assertLessThanOrEqual(Slug::LENGTH, strlen(Slug::latin(str_repeat('щ', 190), 'ru')));
    }

    #[Test]
    public function a_name_that_leaves_nothing_is_handed_on_for_the_model_to_settle(): void
    {
        $this->assertSame('khit', Slug::address('Хіт', 'uk'));
        $this->assertSame('漢字', Slug::address(' 漢字 ', 'ru'));
    }

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
