<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Vacancies\VacanciesServiceProvider;

/**
 * The index (§4.5): the open vacancies in groups by category, in the order of the categories and
 * then of the list; a vacancy in two categories in both; the uncategorised last; a filter of links
 * by the category's key; no 404 for a key nobody has.
 */
final class IndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
    }

    #[Test]
    public function the_open_ones_in_groups_by_category_and_the_rest_last(): void
    {
        $sales = $this->category('sales');
        $development = $this->category('development');
        $hidden = $this->category('hidden', visible: false);
        $sales->update(['position' => 1]);
        $development->update(['position' => 0]);

        $php = $this->vacancy('php');
        $lead = $this->vacancy('team-lead');
        $closer = $this->vacancy('closer');
        $cleaner = $this->vacancy('cleaner');
        $this->vacancy('gone', attributes: ['is_closed' => true])->syncCategories([$sales->id]);
        $this->vacancy('draft', published: false)->syncCategories([$sales->id]);

        $php->syncCategories([$development->id]);
        $lead->syncCategories([$sales->id, $development->id]);
        $closer->syncCategories([$sales->id]);
        // Only in a hidden category: as good as in none.
        $cleaner->syncCategories([$hidden->id]);

        $page = (string) $this->get('/careers')->assertOk()->getContent();

        $this->assertSame(['Development', 'Sales', 'Other vacancies'], $this->headings($page));
        $this->assertSame(['Php', 'Team lead', 'Team lead', 'Closer', 'Cleaner'], $this->links($page));
        $this->assertStringNotContainsString('Gone', $page);
        $this->assertStringNotContainsString('Draft', $page);

        // The filter: All, then every group with a key.
        $this->assertStringContainsString('href="http://localhost/careers?category=development"', $page);
        $this->assertStringContainsString('href="http://localhost/careers?category=sales"', $page);
        $this->assertStringNotContainsString('?category=hidden', $page);

        $list = $this->jsonLd($page, 'ItemList');
        $this->assertIsArray($list);
        $this->assertCount(4, $list['itemListElement']);
    }

    #[Test]
    public function a_key_in_the_address_leaves_one_group_and_an_unknown_one_leaves_none(): void
    {
        $sales = $this->category('sales');
        $development = $this->category('development');

        $this->vacancy('php')->syncCategories([$development->id]);
        $this->vacancy('team-lead')->syncCategories([$sales->id, $development->id]);
        $this->vacancy('loner');

        $page = (string) $this->get('/careers?category=sales')->assertOk()->getContent();

        $this->assertSame(['Sales'], $this->headings($page));
        $this->assertSame(['Team lead'], $this->links($page));
        $this->assertMatchesRegularExpression('#href="[^"]+\?category=sales"\s+aria-current="page"#', $page);
        // The canonical is the index itself.
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/careers">', $page);

        $empty = (string) $this->get('/careers?category=nobody')->assertOk()->getContent();

        $this->assertSame([], $this->headings($empty));
        $this->assertStringContainsString('No open vacancies at the moment.', $empty);
    }

    #[Test]
    public function an_empty_index_says_so(): void
    {
        $this->assertStringContainsString('No open vacancies at the moment.', (string) $this->get('/careers')->assertOk()->getContent());
    }

    #[Test]
    public function an_empty_prefix_is_refused(): void
    {
        $this->app['config']->set('webx-vacancies.prefix', ' / ');

        $this->expectException(InvalidArgumentException::class);

        VacanciesServiceProvider::prefix($this->app['config']);
    }

    /** @return list<string> */
    private function headings(string $page): array
    {
        preg_match_all('#<h2>(.*?)</h2>#', $page, $matches);

        return $matches[1];
    }

    /** @return list<string> */
    private function links(string $page): array
    {
        preg_match_all('#<a class="wx-vacancies__link" href="[^"]*">(.*?)</a>#', $page, $matches);

        return $matches[1];
    }
}
