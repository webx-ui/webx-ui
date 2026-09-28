<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Models\Route;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * The API the panel is written against (§4.11) — the shapes are a contract with the npm half,
 * which is written in parallel from the spec, so the tests read the keys one by one.
 *
 * In an application whose timezone is not UTC: in UTC every mistake about days cancels out.
 */
final class PanelTest extends TestCase
{
    /** The keys of a row, and of `vacancy` in the form (§4.11). */
    private const ROW = [
        'id', 'title', 'slug', 'path', 'url', 'workplace', 'city', 'employment_types', 'valid_through', 'posted_at',
        'closed', 'closed_reason', 'status', 'position', 'categories', 'published_at', 'updated_at', 'deleted_at', 'revision',
    ];

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.timezone', 'Asia/Hong_Kong');
        $app['config']->set('webx-vacancies.country', 'ua');
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
    public function the_list_is_whole_open_by_default_and_names_its_filters(): void
    {
        $design = $this->category('design');
        $open = $this->vacancy('designer', attributes: ['city' => 'Kyiv', 'employment_types' => ['FULL_TIME'], 'valid_through' => '2026-11-30']);
        $open->syncCategories([$design->id]);
        $this->vacancy('closed', attributes: ['is_closed' => true]);
        $this->vacancy('expired', attributes: ['valid_through' => '2026-10-09']);
        $this->vacancy('binned')->delete();

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame(['data', 'filters'], array_keys((array) $response->json()));
        $this->assertSame([['id' => $design->id, 'title' => 'Design']], $response->json('filters.categories'));

        $rows = (array) $response->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame(self::ROW, array_keys($rows[0]));
        $this->assertSame('Designer', $rows[0]['title']);
        $this->assertSame('careers/designer', $rows[0]['path']);
        $this->assertSame('Kyiv', $rows[0]['city']);
        $this->assertSame('2026-11-30', $rows[0]['valid_through']);
        $this->assertSame('2026-10-10', $rows[0]['posted_at']);
        $this->assertFalse($rows[0]['closed']);
        $this->assertNull($rows[0]['closed_reason']);
        $this->assertSame([['id' => $design->id, 'title' => 'Design']], $rows[0]['categories']);

        $closed = (array) $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?state=closed')->assertOk()->json('data');
        $this->assertSame(['manual', 'expired'], array_column($closed, 'closed_reason'));

        $this->assertCount(3, (array) $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?state=all')->json('data'));
        $this->assertSame(['Binned'], array_column((array) $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?trashed=1&state=closed')->json('data'), 'title'));
        $this->assertSame(['Designer'], array_column((array) $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?state=all&category='.$design->id)->json('data'), 'title'));
        $this->assertSame(['Expired'], array_column((array) $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?state=all&q=expi')->json('data'), 'title'));
    }

    #[Test]
    public function a_new_vacancy_starts_on_site_full_time_in_the_first_currency_and_the_sites_country(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'Senior PHP developer'])
            ->assertCreated();

        $this->assertSame(['vacancy', 'values', 'revision', 'prefix', 'preview_url'], array_keys((array) $response->json('data')));
        $this->assertSame(self::ROW, array_keys((array) $response->json('data.vacancy')));
        $this->assertSame('careers', $response->json('data.prefix'));
        $this->assertSame('draft', $response->json('data.vacancy.status'));

        $values = (array) $response->json('data.values');

        $this->assertSame('senior-php-developer', $values['slug']['en']);
        $this->assertSame('onsite', $values['workplace']);
        $this->assertSame(['FULL_TIME'], $values['employment_types']);
        $this->assertSame('USD', $values['salary_currency']);
        $this->assertSame('UA', $values['country']);
        $this->assertFalse($values['is_closed']);
        $this->assertNull($values['valid_through']);
        $this->assertNull($values['posted_at']);
        $this->assertSame([], $values['duties']);
        $this->assertSame([], $values['form']);
        $this->assertArrayHasKey('seo', $values);
    }

    #[Test]
    public function a_save_waits_in_the_draft_and_a_stale_revision_is_a_conflict(): void
    {
        $vacancy = $this->vacancy('designer');
        $revision = (string) $this->actingAs($this->editor(), 'cms')->getJson($this->api($vacancy->id))->json('data.revision');

        $saved = $this->actingAs($this->editor(), 'cms')->putJson($this->api($vacancy->id), [
            'values' => [
                'salary_min' => 1000,
                'salary_max' => 2000,
                'salary_unit' => 'MONTH',
                'country' => 'pl',
                'valid_through' => '2026-11-30',
                'duties' => [['text' => ['en' => 'Draw']], ['text' => ['en' => '  ']], ['text' => ['en' => 'Think']]],
                'employment_types' => ['PART_TIME'],
            ],
            'revision' => $revision,
        ])->assertOk();

        $values = (array) $saved->json('data.values');

        $this->assertSame('PL', $values['country']);
        $this->assertEquals(1000, $values['salary_min']);
        $this->assertSame('2026-11-30', $values['valid_through']);
        // The empty line is gone.
        $this->assertSame([['text' => ['en' => 'Draw']], ['text' => ['en' => 'Think']]], $values['duties']);
        $this->assertSame('modified', $saved->json('data.vacancy.status'));
        // The site still has the old one.
        $this->assertNull($vacancy->refresh()->salary_min);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($vacancy->id), ['values' => ['salary_min' => 5], 'revision' => $revision])
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'data' => ['vacancy', 'values', 'revision']]);
    }

    #[Test]
    public function every_refusal_lands_under_its_own_field(): void
    {
        $vacancy = $this->vacancy('designer', attributes: ['posted_at' => '2026-10-05']);

        $this->refused($vacancy, ['salary_min' => 2000, 'salary_max' => 1000, 'salary_unit' => 'MONTH'], 'salary_max');
        $this->refused($vacancy, ['salary_min' => 2000], 'salary_unit');
        $this->refused($vacancy, ['salary_currency' => 'GBP'], 'salary_currency');
        $this->refused($vacancy, ['country' => 'Ukraine'], 'country');
        $this->refused($vacancy, ['valid_through' => '2026-10-01'], 'valid_through');
        $this->refused($vacancy, ['employment_types' => ['FULL_TIME', 'SOMETIMES']], 'employment_types');
        $this->refused($vacancy, ['workplace' => 'moon'], 'workplace');
        $this->refused($vacancy, ['salary_unit' => 'DECADE'], 'salary_unit');
        $this->refused($vacancy, ['requirements' => [['text' => ['en' => 'Fine']], ['text' => ['en' => str_repeat('x', 501)]]]], 'requirements.1.text.en');
    }

    #[Test]
    public function a_currency_taken_out_of_the_config_does_not_lock_the_vacancy_that_has_it(): void
    {
        $kept = $this->vacancy('kept', attributes: ['salary_currency' => 'PLN']);
        $other = $this->vacancy('other');

        $this->app['config']->set('webx-vacancies.currencies', ['USD' => '$']);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($kept->id), ['values' => ['salary_currency' => 'PLN', 'salary_min' => 100, 'salary_unit' => 'HOUR']])
            ->assertOk()
            ->assertJsonPath('data.values.salary_currency', 'PLN');

        $this->refused($other, ['salary_currency' => 'PLN'], 'salary_currency');
    }

    #[Test]
    public function publishing_puts_the_day_up_once(): void
    {
        $vacancy = $this->vacancy('designer', published: false);

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/publish'))
            ->assertOk()
            ->assertJsonPath('data.posted_at', '2026-10-10')
            ->assertJsonPath('data.status', 'published');

        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Hong_Kong'));

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($vacancy->id), ['values' => ['lead' => ['en' => 'New']]])->assertOk();
        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/publish'))
            ->assertOk()
            ->assertJsonPath('data.posted_at', '2026-10-10');

        // The editor may move it — "we opened the hiring again".
        $this->actingAs($this->editor(), 'cms')->putJson($this->api($vacancy->id), ['values' => ['posted_at' => '2026-10-20']])->assertOk();
        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/publish'))->assertJsonPath('data.posted_at', '2026-10-20');
    }

    #[Test]
    public function closing_and_opening_again_are_one_button_each_and_refuse_pending_edits(): void
    {
        $vacancy = $this->vacancy('designer', attributes: ['valid_through' => '2026-10-01']);

        $this->assertSame('expired', $vacancy->closedReason());

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/close'))
            ->assertOk()
            ->assertJsonPath('data.closed_reason', 'manual')
            ->assertJsonPath('data.status', 'published');

        // Open again: an expired one loses its last day, or the button would change nothing.
        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/reopen'))
            ->assertOk()
            ->assertJsonPath('data.closed', false)
            ->assertJsonPath('data.valid_through', null);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($vacancy->id), ['values' => ['lead' => ['en' => 'Edited']]])->assertOk();

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/close'))
            ->assertStatus(409)
            ->assertJsonPath('message', 'The vacancy has edits that are not on the site yet. Publish or discard them first.');

        $draft = $this->vacancy('draft', published: false);
        $this->actingAs($this->editor(), 'cms')->postJson($this->api($draft->id.'/close'))->assertStatus(409);
        $this->assertFalse($draft->refresh()->isPublished());
    }

    #[Test]
    public function a_copy_is_a_draft_with_everything_but_the_address_the_day_and_the_history(): void
    {
        $this->useLocales('en', 'uk');

        $design = $this->category('design');
        $form = $this->form();
        $source = $this->vacancy('designer', attributes: [
            'slug' => ['en' => 'designer', 'uk' => 'dyzainer'],
            'city' => ['en' => 'Kyiv'],
            'is_closed' => true,
            'salary_currency' => 'UAH',
            'duties' => [['text' => ['en' => 'Draw']]],
        ]);
        $source->syncCategories([$design->id]);
        $source->syncRelated('form', 'inbox-form', [$form->id]);
        $next = $this->vacancy('next');
        $this->vacancy('designer-2', published: false);

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api($source->id.'/duplicate'))->assertCreated();

        $copy = Vacancy::query()->findOrFail($response->json('data.vacancy.id'));

        $this->assertSame('draft', $response->json('data.vacancy.status'));
        $this->assertSame(['en' => 'designer-3', 'uk' => 'dyzainer-2'], $copy->getTranslations('slug'));
        $this->assertSame('Designer', $copy->getTranslation('title', 'en'));
        $this->assertSame('Kyiv', $copy->getTranslation('city', 'en'));
        $this->assertSame('UAH', $copy->salary_currency);
        $this->assertSame([['text' => ['en' => 'Draw']]], $copy->duties);
        $this->assertFalse($copy->is_closed);
        $this->assertNull($copy->posted_at);
        $this->assertSame([$design->id], $copy->categoryIds());
        $this->assertSame([$form->id], $copy->relatedIds('form'));
        $this->assertSame(0, $copy->publishedVersions()->count());

        // Right after the original, and the one after it moved down.
        $this->assertSame($source->position + 1, $copy->position);
        $this->assertSame($source->position + 2, $next->refresh()->position);
    }

    #[Test]
    public function a_copy_refused_half_way_leaves_no_row(): void
    {
        $source = $this->vacancy('designer');
        $next = $this->vacancy('next');
        $before = Vacancy::withTrashed()->count();

        // Another vacancy takes the address the copy is about to be given, behind the check's back.
        $other = $this->vacancy('elsewhere', published: false);

        $this->app['events']->listen('eloquent.saving: '.Vacancy::class, static function (Vacancy $vacancy) use ($other): void {
            if ($vacancy->exists === false) {
                Route::query()->create([
                    'locale' => 'en',
                    'path' => 'careers/designer-2',
                    'kind' => Route::CANONICAL,
                    'entity_type' => $other->getMorphClass(),
                    'entity_id' => $other->id,
                ]);
            }
        });

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($source->id.'/duplicate'))->assertUnprocessable();

        $this->assertSame($before + 1, Vacancy::withTrashed()->count());
        // The list was not left shifted for a copy that never came.
        $this->assertSame($source->position + 1, $next->refresh()->position);
    }

    #[Test]
    public function reorder_takes_a_list_of_ids_and_the_places_they_hold(): void
    {
        $a = $this->vacancy('a');
        $closed = $this->vacancy('closed', attributes: ['is_closed' => true]);
        $b = $this->vacancy('b');
        $c = $this->vacancy('c');

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('reorder'), ['ids' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);

        // The Open tab: the three open ones, the closed one stays between them.
        $this->actingAs($this->editor(), 'cms')->postJson($this->api('reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertNoContent();

        $this->assertSame(['c', 'closed', 'a', 'b'], array_map(
            static fn (Vacancy $vacancy): string => (string) $vacancy->getTranslation('slug', 'en'),
            Vacancy::query()->scopes(['byPosition'])->get()->all(),
        ));
        $this->assertSame($closed->position, $closed->refresh()->position);
    }

    #[Test]
    public function restoring_an_old_version_does_not_move_the_vacancy(): void
    {
        $vacancy = $this->vacancy('designer');
        $this->vacancy('other');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($vacancy->id), ['values' => ['title' => ['en' => 'Two']]])->assertOk();
        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/publish'))->assertOk();

        $vacancy->refresh()->update(['position' => 50]);

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/versions/1/restore'))->assertOk();
        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/publish'))->assertOk();

        $this->assertSame(50, $vacancy->refresh()->position);
        $this->assertSame('Designer', $vacancy->text('title', 'en'));
    }

    #[Test]
    public function the_screens_are_described_with_the_currencies_and_the_seo_card_patched_in(): void
    {
        $root = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/vacancies.form')->assertOk()->json('data.root');
        $nodes = $this->nodes((array) $root);

        $this->assertSame(['USD', 'EUR', 'UAH', 'PLN'], array_column($nodes['salary-currency']['props']['options'], 'value'));
        $this->assertArrayHasKey('seo-fields', $nodes);
        $this->assertArrayHasKey('project-fields', $nodes);
        $this->assertSame('inbox-form', $nodes['form']['props']['target']);

        $category = $this->nodes((array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/vacancies.category-form')->assertOk()->json('data.root'));
        $this->assertSame(['naming', 'title', 'slug', 'is-visible', 'project-fields'], array_keys($category));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function refused(Vacancy $vacancy, array $values, string $field): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($vacancy->id), ['values' => $values])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    }

    /**
     * Every node of a tree by its id.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function nodes(array $nodes): array
    {
        $found = [];

        foreach ($nodes as $node) {
            $found[(string) $node['id']] = $node;
            $found = [...$found, ...$this->nodes((array) ($node['children'] ?? []))];
        }

        return $found;
    }
}
