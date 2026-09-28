<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * `JobPosting` (§4.7): where the work is, what it pays, until when — and nothing at all without an
 * organisation to hire for. In Hong Kong, so that an offset of zero hides nothing.
 */
final class MarkupTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.timezone', 'Asia/Hong_Kong');
        date_default_timezone_set('Asia/Hong_Kong');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Hong_Kong'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        date_default_timezone_set('UTC');
    }

    #[Test]
    public function on_site_work_has_a_place_and_no_telecommute(): void
    {
        $this->organisation();

        $markup = $this->markup([
            'city' => 'Kyiv',
            'address' => '1 Khreshchatyk',
            'country' => 'UA',
            'employment_types' => ['PART_TIME', 'FULL_TIME'],
            'valid_through' => '2026-11-30',
        ]);

        $this->assertSame([
            '@type' => 'Place',
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => '1 Khreshchatyk', 'addressLocality' => 'Kyiv', 'addressCountry' => 'UA'],
        ], $markup['jobLocation']);
        $this->assertArrayNotHasKey('jobLocationType', $markup);
        // Google's order, not the editor's.
        $this->assertSame(['FULL_TIME', 'PART_TIME'], $markup['employmentType']);
        // The whole of the last day where the site is.
        $this->assertSame('2026-11-30T23:59:59+08:00', $markup['validThrough']);
        $this->assertSame(['@id' => 'https://example.test/#organization'], $markup['hiringOrganization']);
    }

    #[Test]
    public function remote_work_is_telecommute_and_hybrid_is_both(): void
    {
        $this->organisation();

        $remote = $this->markup(['workplace' => 'remote', 'city' => 'Kyiv', 'country' => 'PL']);

        $this->assertSame('TELECOMMUTE', $remote['jobLocationType']);
        $this->assertSame(['@type' => 'Country', 'name' => 'PL'], $remote['applicantLocationRequirements']);
        $this->assertArrayNotHasKey('jobLocation', $remote);

        $hybrid = $this->markup(['workplace' => 'hybrid', 'city' => 'Lviv']);

        $this->assertSame('TELECOMMUTE', $hybrid['jobLocationType']);
        $this->assertSame('Lviv', $hybrid['jobLocation']['address']['addressLocality']);
        // No country, no requirement to be in one.
        $this->assertArrayNotHasKey('applicantLocationRequirements', $hybrid);
    }

    #[Test]
    public function the_salary_is_a_range_a_value_or_nothing(): void
    {
        $this->organisation();

        $range = $this->markup(['salary_min' => 40000, 'salary_max' => 60000, 'salary_unit' => 'MONTH', 'salary_currency' => 'UAH']);

        $this->assertSame([
            '@type' => 'MonetaryAmount',
            'currency' => 'UAH',
            'value' => ['@type' => 'QuantitativeValue', 'unitText' => 'MONTH', 'minValue' => 40000.0, 'maxValue' => 60000.0],
        ], $range['baseSalary']);

        $one = $this->markup(['salary_min' => 25, 'salary_unit' => 'HOUR', 'salary_currency' => 'USD']);
        $this->assertSame(['@type' => 'QuantitativeValue', 'unitText' => 'HOUR', 'value' => 25.0], $one['baseSalary']['value']);

        $this->assertArrayNotHasKey('baseSalary', $this->markup(['salary_min' => 25, 'salary_unit' => 'HOUR']));
        $this->assertArrayNotHasKey('baseSalary', $this->markup(['salary_min' => 25, 'salary_currency' => 'USD']));
        // Words are for people.
        $this->assertArrayNotHasKey('baseSalary', $this->markup(['salary' => 'Generous']));
    }

    #[Test]
    public function the_description_carries_the_lists_and_empty_lists_are_no_properties(): void
    {
        $this->organisation();

        $markup = $this->markup([
            'description' => '<p>We write a CMS.</p>',
            'duties' => [['text' => ['en' => 'Write PHP']], ['text' => ['en' => 'Review <code>']]],
            'benefits' => [['text' => ['en' => 'Remote Fridays']]],
        ]);

        $this->assertSame("Write PHP\nReview <code>", $markup['responsibilities']);
        $this->assertSame('Remote Fridays', $markup['jobBenefits']);
        $this->assertArrayNotHasKey('qualifications', $markup);
        $this->assertStringContainsString('<p>We write a CMS.</p>', $markup['description']);
        $this->assertStringContainsString('<h2>Duties</h2><ul><li>Write PHP</li><li>Review &lt;code&gt;</li></ul>', $markup['description']);

        $bare = $this->markup([]);
        $this->assertArrayNotHasKey('employmentType', $bare);
        $this->assertArrayNotHasKey('validThrough', $bare);
        $this->assertArrayNotHasKey('responsibilities', $bare);
    }

    #[Test]
    public function without_an_organisation_there_is_no_markup_at_all(): void
    {
        $vacancy = $this->vacancy('nobody-hires', attributes: ['employment_types' => ['FULL_TIME']]);

        $this->assertSame([], $vacancy->structuredData('en'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function markup(array $attributes): array
    {
        static $n = 0;
        $n++;

        $vacancy = $this->vacancy('job-'.$n, attributes: $attributes);
        $markup = $vacancy->structuredData('en');

        $this->assertCount(1, $markup);
        $this->assertSame('JobPosting', $markup[0]['@type']);

        return $markup[0];
    }
}
