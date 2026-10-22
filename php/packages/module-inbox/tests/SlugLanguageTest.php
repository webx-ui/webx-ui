<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldNames;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;

/**
 * A field's machine name out of its label is transliterated by the label's language, the site's
 * main language first: «Хіт» is `khit` in Ukrainian and `hit` in Russian.
 */
final class SlugLanguageTest extends TestCase
{
    #[Test]
    public function a_field_name_is_transliterated_by_the_language_of_its_label(): void
    {
        Locale::fromCode('uk', ['is_default' => true, 'sort' => 0])->save();
        Locale::fromCode('ru', ['sort' => 1])->save();
        $this->app->make(Locales::class)->forget();

        $this->assertSame('khit', FieldNames::fromTitle(['ru' => 'Хит', 'uk' => 'Хіт'], []));
        $this->assertSame('hit', FieldNames::fromTitle(['ru' => 'Хит'], []));
        $this->assertSame('vash_telefon_2', FieldNames::fromTitle(['ru' => 'Ваш телефон'], ['vash_telefon']));
        $this->assertSame('field_42', FieldNames::fromTitle('42', []));
    }
}
