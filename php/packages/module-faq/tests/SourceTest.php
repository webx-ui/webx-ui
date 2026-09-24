<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Faq\Collections\FaqSource;
use WebxUi\Faq\Models\Question;

/**
 * What a FAQ block is handed (§4.3): who is seen in which language, in what order, and under
 * which anchor.
 */
final class SourceTest extends TestCase
{
    #[Test]
    public function the_source_is_registered_under_its_key(): void
    {
        $source = $this->app->make(CollectionSources::class)->find('faq');

        $this->assertInstanceOf(FaqSource::class, $source);
        $this->assertSame('faq/categories', $source->categories());
        $this->assertTrue($source->supportsMarkup());
        $this->assertSame('faq.view', $source->permission());
    }

    #[Test]
    public function a_question_is_seen_only_in_the_languages_it_has_both_halves_in(): void
    {
        $both = $this->question('Paying by card', 'Оплата картой');
        $english = $this->question('Refunds');
        $noAnswer = $this->question('Delivery', 'Доставка', answer: ['en' => '<p>By courier.</p>', 'ru' => '<p></p>']);
        $this->question('Draft', 'Черновик', published: false);
        $this->question('Binned', 'В корзине')->delete();

        $this->assertSame([$both->id, $english->id, $noAnswer->id], $this->ids('en'));
        $this->assertSame([$both->id], $this->ids('ru'), 'no other language stands in, and an empty paragraph is no answer');

        $item = $this->items(new Selection, 'ru')[0];

        $this->assertSame('Оплата картой', $item['question']);
        $this->assertSame('<p>Про Оплата картой.</p>', $item['answer']);
        $this->assertSame('paying-by-card', $item['anchor']);
        $this->assertSame([], $item['categories']);
    }

    #[Test]
    public function one_category_reads_in_its_own_order_and_two_in_the_order_of_the_whole_list(): void
    {
        $billing = $this->category('Billing');
        $delivery = $this->category('Delivery');

        $refunds = $this->question('Refunds', categories: [$billing, $delivery]);
        $cards = $this->question('Cards', categories: [$billing]);
        $couriers = $this->question('Couriers', categories: [$delivery]);
        $this->question('Unfiled');

        // Inside Billing, Cards was dragged above Refunds.
        $billing->items()->updateExistingPivot($cards->id, ['item_position' => 0]);
        $billing->items()->updateExistingPivot($refunds->id, ['item_position' => 5]);

        $this->assertSame([$cards->id, $refunds->id], $this->ids('en', new Selection([$billing->id])));
        $this->assertSame(
            [$refunds->id, $cards->id, $couriers->id],
            $this->ids('en', new Selection([$billing->id, $delivery->id])),
            'each once, by the whole list',
        );

        // Every category a question is in, not only the chosen one: the filter is built from them.
        $this->assertSame([$billing->id, $delivery->id], $this->items(new Selection([$billing->id]), 'en')[1]['categories']);
    }

    #[Test]
    public function the_limit_counts_what_is_shown(): void
    {
        $this->question('Not in Russian');
        $a = $this->question('A', 'А');
        $b = $this->question('B', 'Б');
        $this->question('C', 'В');

        $this->assertSame([$a->id, $b->id], $this->ids('ru', new Selection(limit: 2)));
    }

    #[Test]
    public function a_new_question_goes_to_the_end_of_the_list(): void
    {
        $first = $this->question('First');
        DB::table('faq_questions')->where('id', $first->id)->update(['position' => 40]);

        $this->assertSame(41, $this->question('Second')->position);
    }

    #[Test]
    public function the_anchor_is_made_once_and_is_never_taken_twice(): void
    {
        $one = $this->question('How do I pay?', 'Как оплатить?');
        $two = $this->question('How do I pay!');
        $binned = $this->question('How do I pay');
        $binned->delete();
        $three = $this->question('How do I pay');

        $this->assertSame('how-do-i-pay', $one->anchor);
        $this->assertSame('how-do-i-pay-2', $two->anchor);
        $this->assertSame('how-do-i-pay-3', $binned->anchor);
        $this->assertSame('how-do-i-pay-4', $three->anchor, 'the bin still holds its anchor for it');

        $one->update(['question' => ['en' => 'Which cards do you take?']]);
        $this->assertSame('how-do-i-pay', $one->refresh()->anchor, 'a link to the question survives its rewording');

        // Made from the default language, whatever the panel is speaking; nothing to make it of
        // is its number.
        $russian = Question::query()->create(['question' => ['ru' => 'Только по-русски'], 'answer' => ['ru' => '<p>Да.</p>']]);
        $this->assertSame('q-'.$russian->id, $russian->refresh()->anchor);
    }

    /**
     * @return list<int>
     */
    private function ids(string $locale, ?Selection $selection = null): array
    {
        return array_map(static fn (array $item): int => (int) $item['id'], $this->items($selection ?? new Selection, $locale));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(Selection $selection, string $locale): array
    {
        return $this->app->make(FaqSource::class)->items($selection, $locale);
    }
}
