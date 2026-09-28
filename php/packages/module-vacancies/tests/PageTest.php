<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * The page of a vacancy (§4.6): open, it is a job posting; closed — by hand or by its last day —
 * it stays, marked, without the posting and out of search (decisions 4, 14); off the site it is
 * gone.
 */
final class PageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
        $this->organisation();
    }

    #[Test]
    public function an_open_vacancy_is_a_page_with_a_job_posting(): void
    {
        $this->vacancy('php-developer', attributes: [
            'lead' => 'Build the panel.',
            'city' => 'Kyiv',
            'employment_types' => ['FULL_TIME', 'PART_TIME'],
            'salary_min' => 40000,
            'salary_max' => 60000,
            'salary_unit' => 'MONTH',
            'salary_currency' => 'UAH',
            'description' => '<p>We write a CMS.</p>',
            'duties' => [['text' => ['en' => 'Write PHP']], ['text' => ['en' => '']]],
            'valid_through' => '2026-11-30',
        ]);

        $page = (string) $this->get('/careers/php-developer')->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Php developer</h1>', $page);
        $this->assertStringContainsString('Build the panel.', $page);
        $this->assertStringContainsString('Full-time, Part-time', $page);
        // No words of the editor's: the numbers, said.
        $this->assertStringContainsString("40\u{00A0}000–60\u{00A0}000 ₴ per month", $page);
        $this->assertStringContainsString('<li>Write PHP</li>', $page);
        $this->assertStringNotContainsString('This vacancy is closed.', $page);
        $this->assertStringNotContainsString('noindex', $page);

        $posting = $this->jsonLd($page, 'JobPosting');
        $this->assertIsArray($posting);
        $this->assertSame('Php developer', $posting['title']);
        $this->assertSame('2026-10-10', $posting['datePosted']);
    }

    #[Test]
    public function the_editors_words_for_the_salary_win_over_the_numbers(): void
    {
        $this->vacancy('sales', attributes: [
            'salary' => 'After the interview',
            'salary_min' => 1000,
            'salary_unit' => 'MONTH',
            'salary_currency' => 'USD',
        ]);

        $page = (string) $this->get('/careers/sales')->assertOk()->getContent();

        $this->assertStringContainsString('After the interview', $page);
        $this->assertStringNotContainsString('per month', $page);
    }

    #[Test]
    public function a_closed_vacancy_stays_marked_without_the_posting_and_out_of_search(): void
    {
        $this->vacancy('closed-by-hand', attributes: ['is_closed' => true]);
        $this->vacancy('expired', attributes: ['valid_through' => '2026-10-09']);
        $this->vacancy('last-day', attributes: ['valid_through' => '2026-10-10']);

        foreach (['/careers/closed-by-hand', '/careers/expired'] as $url) {
            $page = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('This vacancy is closed.', $page, $url);
            $this->assertNull($this->jsonLd($page, 'JobPosting'), $url);
            $this->assertMatchesRegularExpression('#<meta name="robots" content="noindex#', $page, $url);
        }

        // Open the whole of its last day.
        $page = (string) $this->get('/careers/last-day')->assertOk()->getContent();
        $this->assertIsArray($this->jsonLd($page, 'JobPosting'));

        $sitemap = (string) $this->get('/sitemap-vacancy.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/careers/last-day', $sitemap);
        $this->assertStringNotContainsString('/careers/expired', $sitemap);
        $this->assertStringNotContainsString('/careers/closed-by-hand', $sitemap);
        $this->assertStringContainsString('/careers<', (string) $this->get('/sitemap-routes.xml')->assertOk()->getContent());
    }

    #[Test]
    public function a_rule_an_editor_wrote_for_the_address_beats_the_noindex_of_a_closed_one(): void
    {
        $this->vacancy('closed', attributes: ['is_closed' => true]);

        SeoUrl::query()->create(['match_type' => UrlMatcher::EXACT, 'pattern' => '/careers/closed', 'title' => ['en' => 'Still here']]);

        $page = (string) $this->get('/careers/closed')->assertOk()->getContent();

        $this->assertStringNotContainsString('noindex', $page);
    }

    #[Test]
    public function a_draft_a_vacancy_in_the_bin_and_one_without_a_title_here_are_not_found(): void
    {
        $this->vacancy('draft', published: false);
        $this->vacancy('binned')->delete();

        $this->useLocales('en', 'uk');
        $this->vacancy('english-only', attributes: ['slug' => ['en' => 'english-only', 'uk' => 'tilky-anhliiska'], 'title' => ['en' => 'English only']]);

        $this->get('/careers/draft')->assertNotFound();
        $this->get('/careers/binned')->assertNotFound();
        $this->get('/careers/english-only')->assertOk();
        $this->get('/uk/careers/tilky-anhliiska')->assertNotFound();
    }

    #[Test]
    public function the_trail_goes_through_the_index_and_not_through_a_category(): void
    {
        $vacancy = $this->vacancy('designer');
        $vacancy->syncCategories([$this->category('design')->id]);

        $page = (string) $this->get('/careers/designer')->assertOk()->getContent();
        $trail = $this->jsonLd($page, 'BreadcrumbList');

        $this->assertIsArray($trail);
        $this->assertSame(
            ['Home', 'Careers', 'Designer'],
            array_map(static fn (array $item): string => (string) $item['name'], (array) $trail['itemListElement']),
        );
    }
}
