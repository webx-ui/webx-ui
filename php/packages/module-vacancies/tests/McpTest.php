<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * Vacancies by their other doors (§4.12): the same list, the same screen, the same draft, the same
 * copy, the same closing and the same order as the panel's.
 */
final class McpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 12:00:00');
    }

    #[Test]
    public function the_two_sections_offer_their_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            [
                'vacancies_list', 'vacancies_get', 'vacancies_create', 'vacancies_update', 'vacancies_duplicate',
                'vacancies_publish', 'vacancies_unpublish', 'vacancies_discard', 'vacancies_close', 'vacancies_reopen', 'vacancies_delete',
                'vacancies_reorder',
            ],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('vacancies')),
        );

        $this->assertSame(
            ['vacancy_categories_list', 'vacancy_categories_create', 'vacancy_categories_update', 'vacancy_categories_delete', 'vacancy_categories_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('vacancy-categories')),
        );

        $this->assertSame(['vacancies.view', 'vacancies.manage'], $registry->tool('vacancies_list')->permissions());
        $this->assertSame(['vacancies.manage'], $registry->tool('vacancies_close')->permissions());
        $this->assertSame(['vacancies.categories.manage'], $registry->tool('vacancy_categories_create')->permissions());

        // What an agent gets wrong untold: the shape of a day, the site's currencies, the codes.
        foreach (['vacancies_create', 'vacancies_update'] as $tool) {
            $description = $registry->tool($tool)->tool->description;

            $this->assertStringContainsString('YYYY-MM-DD', $description);
            $this->assertStringContainsString('USD, EUR, UAH, PLN', $description);
            $this->assertStringContainsString('FULL_TIME', $description);
            // The inbox is installed here, so there is a form to name.
            $this->assertStringContainsString('inbox_forms_list', $description);
        }

        $this->assertContains('vacancies://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['vacancies.view']);

        $this->agent('vacancies_list', [], $reader)->assertOk();
        $this->agent('vacancies_create', ['title' => 'Developer'], $reader)->assertHasErrors(['[vacancies.manage]']);
    }

    #[Test]
    public function create_writes_a_draft_and_the_links_wait_for_publishing(): void
    {
        $this->useLocales('en', 'ru');
        $development = $this->category('development');
        $form = $this->form('job-application');

        $created = $this->content($this->agent('vacancies_create', [
            'title' => 'PHP developer',
            'values' => [
                'lead' => 'Laravel and a team of five.',
                'city' => ['en' => 'Kyiv', 'ru' => 'Киев'],
                'country' => 'ua',
                'employment_types' => ['FULL_TIME', 'PART_TIME'],
                'salary' => 'from ₴60,000',
                'salary_min' => 60000,
                'salary_max' => 90000,
                'salary_unit' => 'MONTH',
                'salary_currency' => 'uah',
                // A string, a map and { text } all make one line; an empty one is dropped.
                'duties' => ['Write code', ['en' => 'Review code', 'ru' => 'Ревьюить код'], ['text' => 'Plan'], ''],
                'valid_through' => '2026-11-30',
                'categories' => ['development'],
                'form' => 'job-application',
            ],
        ]));

        $this->assertSame('draft', $created['vacancy']['status']);
        $this->assertSame('/careers/php-developer', $created['vacancy']['urls']['en']['path']);
        $this->assertSame(['en' => 'Laravel and a team of five.'], $created['values']['lead']);
        $this->assertSame('onsite', $created['values']['workplace']);
        $this->assertSame('UA', $created['values']['country']);
        $this->assertSame('UAH', $created['values']['salary_currency']);
        $this->assertSame(['FULL_TIME', 'PART_TIME'], $created['values']['employment_types']);
        $this->assertSame(
            [['text' => ['en' => 'Write code']], ['text' => ['en' => 'Review code', 'ru' => 'Ревьюить код']], ['text' => ['en' => 'Plan']]],
            $created['values']['duties'],
        );
        $this->assertSame('2026-11-30', $created['values']['valid_through']);
        $this->assertSame([$development->id], $created['values']['categories']);
        $this->assertSame([$form->id], $created['values']['form']);
        $this->assertSame(['id' => $form->id, 'slug' => 'job-application', 'enabled' => true], $created['vacancy']['form']);

        $vacancy = Vacancy::query()->findOrFail($created['vacancy']['id']);

        // Everything waits in the draft: nothing is linked on the site yet.
        $this->assertSame([], $vacancy->categoryIds());
        $this->assertNull($vacancy->formSlug());

        $published = $this->content($this->agent('vacancies_publish', ['vacancy' => $vacancy->id]));

        $this->assertSame('published', $published['vacancy']['status']);
        // The first publication sets the day it was put up.
        $this->assertSame('2026-10-01', $published['vacancy']['posted_at']);
        // A fresh copy: the one above remembers the relations it has already read.
        $vacancy = Vacancy::query()->findOrFail($vacancy->id);
        $this->assertSame([$development->id], $vacancy->categoryIds());
        $this->assertSame('job-application', $vacancy->formSlug());
    }

    #[Test]
    public function a_value_outside_its_list_is_refused_with_the_list_and_leaves_nothing_behind(): void
    {
        $this->agent('vacancies_create', ['title' => 'Developer', 'values' => ['salary_currency' => 'GBP']])
            ->assertHasErrors(['USD, EUR, UAH, PLN']);

        $this->agent('vacancies_create', ['title' => 'Developer', 'values' => ['employment_types' => ['FULLTIME']]])
            ->assertHasErrors(['FULL_TIME']);

        $this->agent('vacancies_create', ['title' => 'Developer', 'values' => ['salary_unit' => 'MONTHLY']])
            ->assertHasErrors(['MONTH']);

        $this->agent('vacancies_create', ['title' => 'Developer', 'values' => ['workplace' => 'office']])
            ->assertHasErrors(['onsite']);

        $this->agent('vacancies_create', ['title' => 'Developer', 'values' => ['valid_through' => '30.11.2026']])
            ->assertHasErrors(['YYYY-MM-DD']);

        $this->agent('vacancies_create', ['title' => 'Developer', 'values' => ['form' => 'no-such-form']])
            ->assertHasErrors(['inbox_forms_list']);

        // Refused by the screen, after the row was written: the transaction takes it back.
        $this->agent('vacancies_create', ['title' => 'Developer', 'values' => ['salary_min' => 90, 'salary_max' => 60, 'salary_unit' => 'MONTH']])
            ->assertHasErrors(['salary_max']);

        $this->agent('vacancies_create', ['title' => 'Developer', 'values' => ['salary_min' => 90]])
            ->assertHasErrors(['salary_unit']);

        $this->assertSame(0, Vacancy::withTrashed()->count());
    }

    #[Test]
    public function a_currency_the_site_took_out_of_its_list_stays_on_the_vacancy_that_has_it(): void
    {
        $vacancy = $this->vacancy('developer', attributes: ['salary_currency' => 'PLN']);
        $this->app['config']->set('webx-vacancies.currencies', ['USD' => '$']);

        $this->agent('vacancies_update', ['vacancy' => $vacancy->id, 'values' => ['salary_currency' => 'PLN', 'lead' => 'Still in złoty.']])
            ->assertOk();

        $other = $this->vacancy('designer');

        $this->agent('vacancies_update', ['vacancy' => $other->id, 'values' => ['salary_currency' => 'PLN']])
            ->assertHasErrors(['It has: USD']);
    }

    #[Test]
    public function update_writes_the_draft_and_a_write_over_somebody_elses_is_refused(): void
    {
        $vacancy = $this->vacancy('developer');
        $revision = $this->content($this->agent('vacancies_get', ['vacancy' => '/careers/developer']))['revision'];

        $this->agent('vacancies_update', ['vacancy' => $vacancy->id, 'values' => ['lead' => 'First.'], 'revision' => $revision])->assertOk();
        $this->agent('vacancies_update', ['vacancy' => $vacancy->id, 'values' => ['lead' => 'Second.'], 'revision' => $revision])
            ->assertHasErrors(['changed since you read it']);

        $this->agent('vacancies_update', ['vacancy' => $vacancy->id, 'values' => ['blocks' => []]])->assertHasErrors(['no blocks']);

        $got = $this->content($this->agent('vacancies_get', ['vacancy' => $vacancy->id]));

        $this->assertSame('modified', $got['vacancy']['status']);
        $this->assertSame(['en' => 'First.'], $got['values']['lead']);

        // `null` is no form at all, and so is an empty list.
        $form = $this->form();
        $this->agent('vacancies_update', ['vacancy' => $vacancy->id, 'values' => ['form' => $form->id]])->assertOk();
        $this->assertSame([], $this->content($this->agent('vacancies_update', ['vacancy' => $vacancy->id, 'values' => ['form' => null]]))['values']['form']);
    }

    #[Test]
    public function duplicate_is_the_panels_copy(): void
    {
        $sales = $this->category('sales');
        $vacancy = $this->vacancy('developer');
        $vacancy->syncCategories([$sales->id]);

        $this->agent('vacancies_duplicate', ['vacancy' => $vacancy->id, 'dry_run' => true])->assertOk();
        $this->assertSame(1, Vacancy::query()->count());

        $copy = $this->content($this->agent('vacancies_duplicate', ['vacancy' => '/careers/developer']));

        $this->assertSame($vacancy->id, $copy['copied_from']);
        $this->assertSame('draft', $copy['vacancy']['status']);
        $this->assertSame('/careers/developer-2', $copy['vacancy']['urls']['en']['path']);
        $this->assertNull($copy['vacancy']['posted_at']);
        $this->assertSame([$sales->id], $copy['values']['categories']);
    }

    #[Test]
    public function close_and_reopen_are_the_buttons_of_the_row(): void
    {
        $vacancy = $this->vacancy('developer');

        $closed = $this->content($this->agent('vacancies_close', ['vacancy' => $vacancy->id]));

        $this->assertTrue($closed['vacancy']['closed']);
        $this->assertSame('manual', $closed['vacancy']['closed_reason']);
        $this->assertSame('published', $closed['vacancy']['status']);

        $this->assertSame([], array_column($this->content($this->agent('vacancies_list'))['vacancies'], 'id'));
        $this->assertSame([$vacancy->id], array_column($this->content($this->agent('vacancies_list', ['state' => 'closed']))['vacancies'], 'id'));

        $this->assertFalse($this->content($this->agent('vacancies_reopen', ['vacancy' => $vacancy->id]))['vacancy']['closed']);

        // Edits waiting would be published by the button, so it refuses — in the panel's words.
        $this->agent('vacancies_update', ['vacancy' => $vacancy->id, 'values' => ['lead' => 'An edit.']])->assertOk();
        $this->agent('vacancies_close', ['vacancy' => $vacancy->id])->assertHasErrors([(string) __('webx-vacancies::errors.close-with-edits')]);

        // And off the site it would put the vacancy back on it.
        $draft = $this->vacancy('designer', published: false);
        $this->agent('vacancies_close', ['vacancy' => $draft->id])->assertHasErrors([(string) __('webx-vacancies::errors.close-unpublished')]);
    }

    #[Test]
    public function reopening_an_expired_vacancy_clears_its_last_day(): void
    {
        $vacancy = $this->vacancy('developer', attributes: ['valid_through' => '2026-09-20', 'posted_at' => '2026-09-01']);

        $this->assertSame('expired', $this->content($this->agent('vacancies_get', ['vacancy' => $vacancy->id]))['vacancy']['closed_reason']);

        $reopened = $this->content($this->agent('vacancies_reopen', ['vacancy' => $vacancy->id]));

        $this->assertFalse($reopened['vacancy']['closed']);
        $this->assertNull($reopened['vacancy']['valid_through']);
    }

    #[Test]
    public function reorder_hands_the_named_their_own_places(): void
    {
        $first = $this->vacancy('first', attributes: ['position' => 0]);
        $closed = $this->vacancy('closed', attributes: ['position' => 1, 'is_closed' => true]);
        $third = $this->vacancy('third', attributes: ['position' => 2]);

        // The open ones swap; the closed one between them stays where it stands.
        $answer = $this->content($this->agent('vacancies_reorder', ['vacancies' => ['/careers/third', $first->id]]));

        $this->assertSame([$third->id, $closed->id, $first->id], array_column($answer['vacancies'], 'id'));

        $this->agent('vacancies_reorder', ['vacancies' => [999]])->assertHasErrors(['[999]']);
    }

    #[Test]
    public function unpublish_and_delete(): void
    {
        $vacancy = $this->vacancy('developer');

        $this->assertSame('unpublished', $this->content($this->agent('vacancies_unpublish', ['vacancy' => $vacancy->id]))['vacancy']['status']);

        $this->agent('vacancies_delete', ['vacancy' => $vacancy->id, 'dry_run' => true])->assertOk();
        $this->assertFalse($vacancy->fresh()?->trashed());

        $this->agent('vacancies_delete', ['vacancy' => '/careers/developer'])->assertOk();

        $this->assertSame([$vacancy->id], array_column($this->content($this->agent('vacancies_list', ['trashed' => true]))['vacancies'], 'id'));
        $this->agent('vacancies_update', ['vacancy' => $vacancy->id, 'values' => ['lead' => 'x']])->assertHasErrors(['in the bin']);
        $this->agent('vacancies_close', ['vacancy' => $vacancy->id])->assertHasErrors(['in the bin']);
    }

    #[Test]
    public function the_list_filters_by_a_category_named_by_its_slug(): void
    {
        $sales = $this->category('sales');
        $seller = $this->vacancy('seller');
        $seller->syncCategories([$sales->id]);
        $this->vacancy('developer');

        $this->assertSame([$seller->id], array_column($this->content($this->agent('vacancies_list', ['category' => 'sales']))['vacancies'], 'id'));
        $this->agent('vacancies_list', ['category' => 'nothing'])->assertHasErrors(['vacancy_categories_list']);
        $this->agent('vacancies_list', ['state' => 'soon'])->assertHasErrors(['"open"']);
    }

    #[Test]
    public function the_categories_tools_make_a_slug_out_of_the_title(): void
    {
        $answer = $this->content($this->agent('vacancy_categories_create', ['title' => 'Customer support']));

        $this->assertSame(['en' => 'customer-support'], $answer['values']['slug']);
    }

    #[Test]
    public function the_catalogue_lists_the_open_vacancies_and_counts_the_closed(): void
    {
        $this->useLocales('en', 'ru');
        $this->app['config']->set('webx-vacancies.country', 'UA');
        $development = $this->category('development');
        $form = $this->form();

        $open = $this->vacancy('developer', attributes: ['city' => ['en' => 'Kyiv']]);
        $open->syncCategories([$development->id]);
        $open->syncRelated(Vacancy::FORM, Vacancy::FORM_TARGET, [$form->id]);
        $this->vacancy('closed-one', attributes: ['is_closed' => true])->syncCategories([$development->id]);
        $loose = $this->vacancy('loose');

        $catalog = ($this->resource('vacancies://catalog')->handler)();

        $this->assertSame(['USD' => '$', 'EUR' => '€', 'UAH' => '₴', 'PLN' => 'zł'], $catalog['currencies']);
        $this->assertSame('UA', $catalog['country']);
        $this->assertSame(url('careers'), $catalog['index_url']);

        [$category] = $catalog['categories'];

        $this->assertSame('development', $category['slug']);
        $this->assertSame(1, $category['closed_count']);
        $this->assertSame([$open->id], array_column($category['open'], 'id'));
        $this->assertSame('Kyiv', $category['open'][0]['city']);
        $this->assertSame(['en'], $category['open'][0]['written_in']);
        $this->assertSame('job-application', $category['open'][0]['form']);
        $this->assertSame([$loose->id], array_column($catalog['uncategorised']['open'], 'id'));
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource {$uri}.");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
