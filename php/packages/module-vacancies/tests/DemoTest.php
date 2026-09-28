<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Inbox\Models\Form;
use WebxUi\Vacancies\Demo\VacanciesDemo;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;

/**
 * The demo vacancies (§4.13): the rules they are there to show, the days counted from the day of
 * seeding, and the application form made through the journal where the inbox is installed.
 */
final class DemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->useLocales('en', 'ru');
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_asks_for_what_is_installed(): void
    {
        $this->assertSame(['inbox'], $this->app->make(VacanciesDemo::class)->requires());
    }

    #[Test]
    public function it_seeds_three_categories_and_seven_vacancies_each_showing_a_rule(): void
    {
        Carbon::setTestNow('2026-10-01 15:00:00');

        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(
            ['development', 'sales', 'support'],
            VacancyCategory::query()->ordered()->get()->map(static fn (VacancyCategory $category): string => (string) $category->getTranslation('slug', 'en'))->all(),
        );
        $this->assertSame('Разработка', VacancyCategory::query()->ordered()->firstOrFail()->getTranslation('title', 'ru'));
        $this->assertSame(7, Vacancy::query()->count());
        $this->assertSame(6, Vacancy::query()->whereNotNull('published_at')->count());

        // An office job with a monthly range in hryvnias, open 45 days from today.
        $php = $this->named('php-developer');
        $this->assertSame(Vacancy::ONSITE, $php->workplace);
        $this->assertSame(['FULL_TIME'], $php->employment());
        $this->assertSame(['UAH', 'MONTH', 60000.0, 90000.0], [$php->salary_currency, $php->salary_unit, (float) $php->salary_min, (float) $php->salary_max]);
        $this->assertSame('2026-11-15', $php->valid_through?->toDateString());
        // The day it was put up is the demo's, not the day of publishing.
        $this->assertSame('2026-09-21', $php->posted_at?->toDateString());
        $this->assertSame(['Write and review code in Laravel 13', 'Design tables and migrations', 'Take part in planning every two weeks'], $php->lines('duties', 'en'));
        $this->assertSame('Писать и ревьюить код на Laravel 13', $php->lines('duties', 'ru')[0]);

        // Remote, by the hour in dollars, in English only and in no category.
        $contractor = $this->named('frontend-contractor');
        $this->assertSame(Vacancy::REMOTE, $contractor->workplace);
        $this->assertSame(['HOUR', 'USD'], [$contractor->salary_unit, $contractor->salary_currency]);
        $this->assertSame(['en'], array_keys($contractor->getTranslations('title')));
        $this->assertSame([], $contractor->categoryIds());

        // Hybrid, part-time, and a salary in words only.
        $support = $this->named('support-specialist');
        $this->assertSame([Vacancy::HYBRID, ['PART_TIME']], [$support->workplace, $support->employment()]);
        $this->assertNull($support->salary_min);
        $this->assertSame('По результатам собеседования', $support->getTranslation('salary', 'ru'));

        $this->assertCount(2, $this->named('sales-engineer')->categoryIds());

        $this->assertSame(Vacancy::CLOSED_MANUAL, $this->named('sales-manager')->closedReason());

        // Its last day was the day before yesterday.
        $expired = $this->named('account-manager');
        $this->assertSame('2026-09-29', $expired->valid_through?->toDateString());
        $this->assertSame(Vacancy::CLOSED_EXPIRED, $expired->closedReason());

        $this->assertSame('draft', $this->named('office-manager')->status());

        // The careers page: the open ones in the demo's order.
        $this->assertSame(
            ['php-developer', 'frontend-contractor', 'support-specialist', 'sales-engineer', 'office-manager'],
            Vacancy::query()->scopes(['open', 'byPosition'])->get()->map(static fn (Vacancy $vacancy): string => (string) $vacancy->getTranslation('slug', 'en'))->all(),
        );
    }

    #[Test]
    public function the_open_vacancies_take_its_own_application_form(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $form = Form::query()->where('slug', 'job-application')->firstOrFail();

        $this->assertSame('Отклик на вакансию', $form->getTranslation('title', 'ru'));
        $this->assertSame(
            ['name', 'email', 'phone', 'resume', 'letter', 'vacancy'],
            $form->fields()->orderBy('position')->pluck('name')->all(),
        );
        $this->assertSame(['pdf', 'doc', 'docx'], $form->fields()->where('name', 'resume')->firstOrFail()->option('extensions'));

        $this->assertSame('job-application', $this->named('php-developer')->formSlug());
        $this->assertSame('job-application', $this->named('frontend-contractor')->formSlug());
        // A closed vacancy has no form to answer with.
        $this->assertNull($this->named('sales-manager')->formSlug());
    }

    #[Test]
    public function a_form_of_that_name_is_the_sites_own_and_is_only_chosen(): void
    {
        $own = $this->form('job-application');

        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(1, Form::query()->where('slug', 'job-application')->count());
        $this->assertSame('job-application', $this->named('php-developer')->formSlug());

        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        // Removing the demo takes its vacancies and leaves the site's form where it was.
        $this->assertSame(0, Vacancy::withTrashed()->count());
        $this->assertNotNull($own->fresh());
    }

    #[Test]
    public function the_demo_is_removed_whole(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Vacancy::withTrashed()->count());
        $this->assertSame(0, VacancyCategory::withTrashed()->count());
        $this->assertSame(0, Form::query()->where('slug', 'job-application')->count());
    }

    #[Test]
    public function it_leaves_a_site_with_vacancies_alone(): void
    {
        $this->vacancy('somebodys-own');

        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(1, Vacancy::query()->count());
        $this->assertSame(0, VacancyCategory::query()->count());
    }

    private function named(string $slug): Vacancy
    {
        return Vacancy::query()->where('slug->en', $slug)->with('categories')->firstOrFail();
    }
}
