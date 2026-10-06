<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;
use WebxUi\Faq\Audit\QuestionContentSource;

/**
 * What the site audit reads of the questions: every one, hidden ones too, each language of the
 * answer a field — and a fix writes the answer back in that language only.
 */
final class AuditSourceTest extends TestCase
{
    #[Test]
    public function every_question_hands_over_its_text_in_each_language(): void
    {
        $shown = $this->question('Paying by card', 'Оплата картой', answer: [
            'en' => '<p>See <a href="https://dev.shop.test/pay">pay</a>.</p>',
            'ru' => '<p>Смотрите оплату.</p>',
        ]);
        $hidden = $this->question('Draft', published: false);
        $this->question('Binned')->delete();

        $source = new QuestionContentSource;
        $records = iterator_to_array($source->records(), false);

        $this->assertSame('faq', $source->id());
        $this->assertSame([(string) $shown->id, (string) $hidden->id], array_map(static fn (ContentRecord $record): string => $record->id, $records));
        $this->assertSame('Paying by card', $records[0]->label);
        $this->assertTrue($records[0]->published);
        $this->assertFalse($records[1]->published);
        $this->assertSame('/faq?question='.$shown->id, $records[0]->editUrl);

        $answers = array_values(array_filter(
            iterator_to_array($source->fields($records[0]), false),
            static fn (ContentField $field): bool => $field->name === 'answer',
        ));

        $this->assertSame(['en', 'ru'], array_map(static fn (ContentField $field): ?string => $field->locale, $answers));
        $this->assertStringContainsString('https://dev.shop.test/pay', $answers[0]->value);
    }

    #[Test]
    public function a_fix_writes_back_one_language(): void
    {
        $question = $this->question('Paying by card', 'Оплата картой', answer: [
            'en' => '<p><a href="https://dev.shop.test/pay">pay</a></p>',
            'ru' => '<p>https://dev.shop.test/pay</p>',
        ]);

        $source = new QuestionContentSource;
        $record = $source->find((string) $question->id);

        $this->assertNotNull($record);
        $this->assertNull($source->find('999999'));

        $source->replace($record, new ContentField('answer', '<p><a href="https://shop.test/pay">pay</a></p>', 'en'), '<p><a href="https://shop.test/pay">pay</a></p>');

        $question->refresh();
        $this->assertSame('<p><a href="https://shop.test/pay">pay</a></p>', $question->getTranslation('answer', 'en'));
        $this->assertSame('<p>https://dev.shop.test/pay</p>', $question->getTranslation('answer', 'ru'));
    }
}
