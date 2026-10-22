<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Faq\Models\Question;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;

/**
 * A question's anchor out of its words is transliterated by the site's main language: «Хіт» is
 * `khit` in Ukrainian — never `xit`, the English reading of Cyrillic.
 */
final class SlugLanguageTest extends TestCase
{
    #[Test]
    public function the_anchor_is_made_of_the_question_in_the_sites_main_language(): void
    {
        Locale::fromCode('uk', ['is_default' => true, 'sort' => 0])->save();
        Locale::fromCode('ru', ['sort' => 1])->save();
        $this->app->make(Locales::class)->forget();

        $question = Question::query()->create([
            'question' => ['ru' => 'Хит сезона?', 'uk' => 'Хіт сезону?'],
            'answer' => ['uk' => '<p>Так.</p>'],
        ]);

        $this->assertSame('khit-sezonu', $question->anchor);
    }
}
