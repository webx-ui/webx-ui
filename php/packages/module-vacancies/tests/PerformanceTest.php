<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cache\CacheGroups;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * The query budget of every public page of the vacancies, and the freshness of what they cache
 * (WEBX_UI_PERFORMANCE_AUDIT.md §7).
 *
 * Every budget is a ceiling the page fits under today with thirty vacancies, not a target, and
 * the point of each is that it does not grow with the rows: a page that asks the registry or the
 * relations once per card or once per group is caught by `assertNoRepeatedQueries()` on the warm
 * pass whatever the count. A change that needs more says why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    private const VACANCIES = 30;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The caches on, the way a site has them: the budget of a warm page is the point.
        $app['config']->set('webx-localization.cache.enabled', true);
        $app['config']->set('webx-seo.cache.enabled', true);
    }

    #[Test]
    public function the_index_fits_its_budget_cold_and_warm(): void
    {
        $this->seedVacancies();

        $this->cold();
        $this->assertQueriesWithin(12, fn () => $this->get('/careers')->assertOk());

        $this->assertQueriesWithin(7, fn () => $this->get('/careers')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/careers')->assertOk());
    }

    /** The filtered index is one group of the whole one, not the list read a second time. */
    #[Test]
    public function a_filtered_index_fits_its_budget_cold_and_warm(): void
    {
        $this->seedVacancies();

        $this->cold();
        $this->assertQueriesWithin(13, fn () => $this->get('/careers?category=development')->assertOk());

        $this->assertQueriesWithin(8, fn () => $this->get('/careers?category=development')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/careers?category=development')->assertOk());
    }

    #[Test]
    public function a_vacancy_fits_its_budget_cold_and_warm(): void
    {
        $this->seedVacancies();

        $this->cold();
        $this->assertQueriesWithin(12, fn () => $this->get('/careers/vacancy-2')->assertOk());

        $this->assertQueriesWithin(7, fn () => $this->get('/careers/vacancy-2')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/careers/vacancy-2')->assertOk());
    }

    #[Test]
    public function the_sitemap_fits_its_budget(): void
    {
        $this->seedVacancies();

        // The build: the vacancies, chunked, with their cards in bulk; the date is a column.
        $this->cold();
        $this->assertQueriesWithin(10, fn () => $this->get('/sitemap.xml')->assertOk());
        // Served from the cache: nothing but the request's own infrastructure.
        $this->assertQueriesWithin(2, fn () => $this->get('/sitemap-vacancy.xml')->assertOk());
    }

    /**
     * The registry and the shared data of a page are cached on versioned keys; a save through
     * the panel moves their groups, and the next request — in the same process, the way a worker
     * serves it — reads the new rows.
     */
    #[Test]
    public function a_page_shows_a_change_made_through_the_panel_at_once(): void
    {
        $this->seedVacancies();
        $editor = $this->editor();
        $slug = 'vacancy-'.self::VACANCIES;
        $title = 'Vacancy '.self::VACANCIES;

        $this->get('/careers')->assertOk()->assertSee($title);
        $this->get('/careers/'.$slug)->assertOk()->assertSee($title);

        $vacancy = Vacancy::query()->where('slug->en', $slug)->firstOrFail();
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($vacancy->getKey()), ['values' => ['title' => ['en' => 'The newest, renamed'], 'slug' => ['en' => 'the-newest']]])
            ->assertOk();
        $this->actingAs($editor, 'cms')->postJson($this->api($vacancy->getKey().'/publish'))->assertOk();

        $this->get('/careers')->assertOk()->assertSee('The newest, renamed')->assertDontSee('>'.$title.'<', false);
        $this->get('/careers/the-newest')->assertOk()->assertSee('The newest, renamed');
        $this->get('/careers/'.$slug)->assertRedirect('/careers/the-newest');
    }

    /**
     * Two categories, an application form and thirty open vacancies: two of three filed under a
     * category, the rest in "Other vacancies"; every second one with the form attached; all with
     * a place, a salary and the three lists — the way the demo has them.
     */
    private function seedVacancies(): void
    {
        // A fixed day, so that the last day of every vacancy stays ahead of it.
        Carbon::setTestNow('2026-10-17 12:00:00');

        $development = $this->category('development');
        $sales = $this->category('sales');
        $form = $this->form('job-application');
        $this->organisation('Example Company');

        for ($i = 1; $i <= self::VACANCIES; $i++) {
            $vacancy = new Vacancy([
                'title' => "Vacancy {$i}",
                'slug' => "vacancy-{$i}",
                'lead' => "The lead of vacancy {$i}.",
                'workplace' => $i % 3 === 0 ? Vacancy::REMOTE : Vacancy::ONSITE,
                'city' => 'Springfield',
                'address' => '1 Main Street',
                'employment_types' => ['FULL_TIME'],
                'salary_min' => 1000 + $i,
                'salary_max' => 2000 + $i,
                'salary_unit' => 'MONTH',
                'salary_currency' => 'EUR',
                'description' => "<p>What vacancy {$i} is about.</p>",
                'duties' => [['text' => ['en' => 'Build things']], ['text' => ['en' => 'Fix things']]],
                'requirements' => [['text' => ['en' => 'Two years of practice']]],
                'benefits' => [['text' => ['en' => 'Remote days']]],
                'valid_through' => '2027-01-31',
                'posted_at' => '2026-10-01',
            ]);
            $vacancy->save();

            if ($i % 3 !== 0) {
                $vacancy->syncCategories([($i % 2 === 0 ? $development : $sales)->getKey()]);
            }

            if ($i % 2 === 0) {
                $vacancy->syncRelated(Vacancy::FORM, Vacancy::FORM_TARGET, [$form->getKey()]);
            }

            $vacancy->publish();
        }
    }

    /** The first visitor after a deploy: an empty store, and nothing read in this process either. */
    private function cold(): void
    {
        Cache::flush();
        $this->app->make(CacheGroups::class)->forgetMemo();
    }
}
