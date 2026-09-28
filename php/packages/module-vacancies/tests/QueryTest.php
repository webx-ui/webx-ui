<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * `vacancies()` (§4.8): every step of the table, and the card a template gets.
 */
final class QueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
    }

    #[Test]
    public function the_open_ones_by_default_and_the_others_when_asked(): void
    {
        $this->vacancy('first');
        $this->vacancy('closed', attributes: ['is_closed' => true]);
        $this->vacancy('expired', attributes: ['valid_through' => '2026-10-01']);
        $this->vacancy('second');
        $this->vacancy('draft', published: false);

        $this->assertSame(['First', 'Second'], $this->titles(vacancies()->get()));
        $this->assertSame(['First', 'Second'], $this->titles(vacancies()->open()->get()));
        $this->assertSame(['Closed', 'Expired'], $this->titles(vacancies()->closed()->get()));
        $this->assertSame(['First', 'Closed', 'Expired', 'Second'], $this->titles(vacancies()->all()->get()));
        $this->assertCount(2, vacancies());
    }

    #[Test]
    public function in_only_except_take_and_locale(): void
    {
        $this->useLocales('en', 'uk');

        $development = $this->category('development');
        $sales = $this->category('sales');

        $a = $this->vacancy('a');
        $b = $this->vacancy('b', attributes: ['title' => ['en' => 'B', 'uk' => 'Б'], 'slug' => ['en' => 'b', 'uk' => 'b']]);
        $c = $this->vacancy('c');
        $d = $this->vacancy('d');

        $a->syncCategories([$development->id]);
        $b->syncCategories([$sales->id, $development->id]);
        $c->syncCategories([$sales->id]);

        $this->assertSame(['A', 'B'], $this->titles(vacancies()->in('development')->get()));
        $this->assertSame(['B', 'C'], $this->titles(vacancies()->in($sales->id)->get()));
        $this->assertSame(['A', 'B', 'C'], $this->titles(vacancies()->in([$sales, 'development'])->get()));
        $this->assertSame([], vacancies()->in('nobody')->get());
        $this->assertSame(['D', 'B'], $this->titles(vacancies()->only([$d->id, $b->id])->get()));
        $this->assertSame(['A', 'C', 'D'], $this->titles(vacancies()->except($b)->get()));
        $this->assertSame(['A', 'B'], $this->titles(vacancies()->take(2)->get()));

        // The limit counts what is shown: only B is written in Ukrainian.
        $this->assertSame(['Б'], $this->titles(vacancies()->locale('uk')->take(2)->get()));
        $this->assertSame('B', vacancies()->only([$b->id])->first()['title'] ?? null);
    }

    #[Test]
    public function groups_follow_the_categories_and_take_counts_per_group(): void
    {
        $development = $this->category('development');
        $sales = $this->category('sales');

        foreach (['a', 'b', 'c'] as $slug) {
            $this->vacancy($slug)->syncCategories([$development->id]);
        }

        $this->vacancy('s')->syncCategories([$sales->id, $development->id]);
        $this->vacancy('loose');

        $groups = vacancies()->take(2)->groups();

        $this->assertSame(['Development', 'Sales', 'Other vacancies'], array_column($groups, 'title'));
        $this->assertSame(['development', 'sales', null], array_column($groups, 'slug'));
        $this->assertSame(['A', 'B'], $this->titles($groups[0]['vacancies']));
        $this->assertSame(['S'], $this->titles($groups[1]['vacancies']));
        $this->assertSame(['Loose'], $this->titles($groups[2]['vacancies']));

        $only = vacancies()->in('sales')->groups();
        $this->assertSame(['Sales'], array_column($only, 'title'));
    }

    #[Test]
    public function a_card_is_plain_data(): void
    {
        $category = $this->category('development');
        $form = $this->form('job-application');

        $vacancy = $this->vacancy('php', attributes: [
            'lead' => 'Build it.',
            'workplace' => 'hybrid',
            'city' => 'Kyiv',
            'employment_types' => ['FULL_TIME'],
            'salary' => 'from 3 000 $',
            'salary_min' => 3000,
            'salary_unit' => 'MONTH',
            'salary_currency' => 'USD',
            'valid_through' => '2026-11-30',
        ]);
        $vacancy->syncCategories([$category->id]);
        $vacancy->syncRelated('form', 'inbox-form', [$form->id]);

        $card = vacancies()->first();

        $this->assertIsArray($card);
        $this->assertSame([
            'id' => $vacancy->id,
            'url' => 'http://localhost/careers/php',
            'title' => 'Php',
            'lead' => 'Build it.',
            'workplace' => 'hybrid',
            'city' => 'Kyiv',
            'address' => '',
            'employment_types' => ['FULL_TIME'],
            'employment' => ['Full-time'],
            'salary' => 'from 3 000 $',
            'salary_range' => ['min' => 3000.0, 'max' => null, 'unit' => 'MONTH', 'currency' => 'USD', 'symbol' => '$'],
            'valid_through' => '2026-11-30',
            'posted_at' => '2026-10-10',
            'closed' => false,
            'categories' => [$category->id],
            'category_names' => ['Development'],
            'form' => 'job-application',
            'fields' => [],
        ], $card);

        // A disabled form is no form to print.
        $form->update(['is_enabled' => false]);
        $card = vacancies()->first();
        $this->assertIsArray($card);
        $this->assertArrayHasKey('form', $card);
        $this->assertNull($card['form']);
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<string>
     */
    private function titles(array $cards): array
    {
        return array_map(static fn (array $card): string => (string) $card['title'], $cards);
    }
}
