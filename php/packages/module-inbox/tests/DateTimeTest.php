<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\DateTimeField;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Widgets\Widgets;

/**
 * The «Date and time» field (FORM_STEPS §6): a day and a time of the field's grid, not in the past,
 * in the site's zone — checked by the intake, kept with its zone and its moment in UTC, sorted by
 * that moment, set up in the panel and through MCP with the same plan.
 */
final class DateTimeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The site's zone, two hours ahead of UTC in winter.
        $app['config']->set('app.timezone', 'Europe/Kyiv');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'ru']]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 10 December 2026, 10:10 in Kyiv.
        Carbon::setTestNow(Carbon::parse('2026-12-10 08:10:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function an_answer_on_the_grid_is_kept_with_its_zone_and_its_moment_in_utc(): void
    {
        $this->meeting();

        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-14', 'time' => '10:30']]])->assertOk();

        $value = Submission::query()->sole()->values->sole();
        $this->assertSame('datetime', $value->type);
        $this->assertSame('12/14/2026, 10:30 AM (Europe/Kyiv)', $value->value);
        $this->assertSame(['date' => '2026-12-14', 'time' => '10:30', 'timezone' => 'Europe/Kyiv', 'at' => '2026-12-14T08:30:00Z'], $value->payload);
    }

    #[Test]
    public function the_value_is_written_in_the_language_the_submission_came_in(): void
    {
        $this->meeting();

        $this->postJson($this->intake(), ['webx_locale' => 'ru', 'fields' => ['meeting' => ['date' => '2026-12-14', 'time' => '10:30']]])->assertOk();

        $this->assertSame('14.12.2026, 10:30 (Europe/Kyiv)', Submission::query()->sole()->values->sole()->value);
    }

    #[Test]
    public function a_time_off_the_grid_a_day_not_offered_and_a_time_gone_today_are_refused(): void
    {
        $this->meeting();
        $refused = fn (array $answer, string $words) => $this->postJson($this->intake(), ['fields' => ['meeting' => $answer]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['fields.meeting' => [$words]]);

        // Between two times, before the first, the last step past the end.
        $refused(['date' => '2026-12-14', 'time' => '10:15'], 'Choose one of the times offered.');
        $refused(['date' => '2026-12-14', 'time' => '08:30'], 'Choose one of the times offered.');
        $refused(['date' => '2026-12-14', 'time' => '17:00'], 'Choose one of the times offered.');
        // Saturday.
        $refused(['date' => '2026-12-12', 'time' => '10:30'], 'Choose a weekday: Mon–Fri.');
        // Today, 10:00 in Kyiv is gone; yesterday too.
        $refused(['date' => '2026-12-10', 'time' => '10:00'], 'This time has already passed. Choose a later one.');
        $refused(['date' => '2026-12-09', 'time' => '11:00'], 'This time has already passed. Choose a later one.');
        // One part without the other, and what is no date.
        $refused(['date' => '2026-12-14'], 'Choose a time.');
        $refused(['time' => '10:30'], 'Choose a day.');
        $refused(['date' => '2026-02-30', 'time' => '10:30'], 'Choose a day and a time from the calendar.');
        $refused(['date' => '', 'time' => ''], 'The Meeting field is required.');

        // 10:30 today is still to come.
        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-10', 'time' => '10:30']]])->assertOk();
        $this->assertSame(1, Submission::query()->count());
    }

    #[Test]
    public function the_past_is_counted_in_the_site_zone_and_not_in_utc(): void
    {
        // 23:30 of Thursday in UTC is already Friday 01:30 in Kyiv.
        Carbon::setTestNow(Carbon::parse('2026-12-10 23:30:00', 'UTC'));
        $this->meeting();

        // 09:00 of Friday in Kyiv is to come, though Friday has not begun in UTC… and Thursday is over.
        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-11', 'time' => '09:00']]])->assertOk();
        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-10', 'time' => '16:30']]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['fields.meeting' => ['This time has already passed. Choose a later one.']]);
    }

    #[Test]
    public function an_optional_field_left_empty_writes_nothing_and_a_page_without_javascript_comes_back_with_the_error(): void
    {
        $form = $this->meeting();
        Field::query()->where('name', 'meeting')->update(['is_required' => false]);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Alex', 'meeting' => ['date' => '', 'time' => '']]])->assertOk();
        $this->assertSame(['name'], Submission::query()->sole()->values->pluck('name')->all());

        // Without JavaScript: a redirect back, the error under the field, what was sent in its controls.
        $this->post($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-12', 'time' => '10:30']]], ['referer' => url('/page')])
            ->assertRedirect()
            ->assertSessionHasErrors(['fields.meeting' => 'Choose a weekday: Mon–Fri.']);
        $this->assertSame(1, $form->submissions()->count());
    }

    #[Test]
    public function the_page_prints_the_grid_of_the_field_in_the_site_zone(): void
    {
        $this->meeting();

        $html = $this->page('<x-webx-inbox::form slug="contact" />');

        $this->assertStringContainsString('name="fields[meeting][date]"', $html);
        $this->assertStringContainsString('min="2026-12-10"', $html);
        preg_match_all('~<option value="(\d\d:\d\d)"~', $html, $times);
        $this->assertSame(['09:00', '09:30'], array_slice($times[1], 0, 2));
        $this->assertSame('16:30', end($times[1]));
        $this->assertStringContainsString('Times are in Europe/Kyiv (UTC+2)', $html);
        $this->assertStringContainsString('&quot;weekdays&quot;:[1,2,3,4,5]', $html);

        app(Widgets::class)->flush();
        Blade::render('<x-webx-inbox::form slug="contact" />');
        $this->assertContains('datetime', app(Widgets::class)->claimed());
    }

    #[Test]
    public function the_list_sorts_the_column_by_the_moment_not_by_its_words(): void
    {
        $form = $this->meeting();
        Field::query()->where('name', 'meeting')->update(['in_table' => true]);

        // Written in this order: the words of 02.01 stand before those of 12.12 — the moments do not.
        foreach ([['2027-01-04', '09:00'], ['2026-12-14', '16:00'], ['2026-12-14', '09:30']] as [$date, $time]) {
            $this->postJson($this->intake(), ['webx_locale' => 'ru', 'fields' => ['meeting' => ['date' => $date, 'time' => $time]]])->assertOk();
        }

        $rows = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api("forms/{$form->id}/submissions?sort=values.meeting"))
            ->assertOk()
            ->json('data');

        $this->assertSame(
            ['14.12.2026, 9:30 (Europe/Kyiv)', '14.12.2026, 16:00 (Europe/Kyiv)', '04.01.2027, 9:00 (Europe/Kyiv)'],
            array_map(static fn (array $row): string => $row['values']['meeting'], $rows),
        );
    }

    #[Test]
    public function the_panel_keeps_the_grid_and_refuses_one_with_no_room(): void
    {
        $form = $this->form('setup', [['name' => 'name', 'type' => FieldType::Text]]);
        $editor = $this->editor();
        $save = fn (array $options) => $this->actingAs($editor, 'cms')->postJson($this->api("forms/{$form->id}/fields"), [
            'name' => 'visit',
            'type' => 'datetime',
            'title' => ['en' => 'Visit'],
            'placeholder' => ['en' => 'Never shown'],
            'options' => $options,
        ]);

        $save(['time-step' => 45])->assertStatus(422)->assertJsonPath('errors', ['options.time-step' => ['The step of the times is 15, 20, 30, 60, 90 or 120 minutes.']]);
        $save(['time-from' => '17:00', 'time-to' => '09:00'])->assertStatus(422)->assertJsonValidationErrors(['options.time-from']);
        $save(['time-step' => 120, 'time-from' => '16:00', 'time-to' => '17:00'])->assertStatus(422)->assertJsonValidationErrors(['options.time-from']);
        $save(['time-to' => '25:00'])->assertStatus(422)->assertJsonValidationErrors(['options.time-to']);
        $save(['weekdays' => [1, 1, 2]])->assertStatus(422)->assertJsonPath('errors', ['options.weekdays' => ['Each day of the week once, from 1 (Monday) to 7 (Sunday).']]);
        $save(['weekdays' => [0, 8]])->assertStatus(422)->assertJsonValidationErrors(['options.weekdays']);

        $save(['time-step' => '60', 'time-from' => '9:00', 'time-to' => '13:00', 'weekdays' => ['2', 4], 'timezone-label' => ['en' => ' Time of the office '], 'choices' => [['value' => 'x']]])
            ->assertCreated()
            ->assertJsonPath('data.options', ['time-step' => 60, 'time-from' => '09:00', 'time-to' => '13:00', 'weekdays' => [2, 4], 'timezone-label' => ['en' => 'Time of the office']]);

        // No options at all are the defaults, and those have room.
        $this->actingAs($editor, 'cms')->postJson($this->api("forms/{$form->id}/fields"), ['type' => 'datetime', 'title' => ['en' => 'Call']])->assertCreated();

        $this->actingAs($editor, 'cms')->getJson($this->api("forms/{$form->id}"))->assertOk()->assertJsonPath('data.timezone', 'Europe/Kyiv');
    }

    #[Test]
    public function an_agent_writes_the_field_and_the_dry_run_says_the_times_of_a_day(): void
    {
        $fields = [
            ['type' => 'datetime', 'name' => 'meeting', 'title' => ['en' => 'Meeting'], 'is_required' => true, 'options' => ['time-step' => 30, 'time-from' => '09:00', 'time-to' => '17:00', 'weekdays' => [1, 2, 3, 4, 5]]],
        ];

        $plan = $this->content($this->agent('form_save', ['slug' => 'agent', 'title' => 'Agent', 'fields' => $fields, 'dry_run' => true])->assertOk());
        $this->assertSame(['meeting' => '09:00, 09:30 … 16:30, Mon–Fri (Europe/Kyiv)'], $plan['times']);
        $this->assertSame(0, Form::query()->count());

        $refused = $this->agent('form_save', ['slug' => 'agent', 'title' => 'Agent', 'fields' => [[...$fields[0], 'options' => ['time-step' => 7]]]]);
        $refused->assertHasErrors(['The step of the times is 15, 20, 30, 60, 90 or 120 minutes.']);

        $saved = $this->content($this->agent('form_save', ['slug' => 'agent', 'title' => 'Agent', 'fields' => $fields])->assertOk());
        $this->assertSame('datetime', $saved['fields'][0]['type']);
        $this->assertSame([1, 2, 3, 4, 5], $saved['fields'][0]['options']['weekdays']);

        $this->postJson($this->intake('agent'), ['fields' => ['meeting' => ['date' => '2026-12-14', 'time' => '10:30']]])->assertOk();
        $got = $this->content($this->agent('get', ['submission' => Submission::query()->sole()->getKey()])->assertOk());
        $this->assertSame(['date' => '2026-12-14', 'time' => '10:30', 'timezone' => 'Europe/Kyiv', 'at' => '2026-12-14T08:30:00Z'], $got['values'][0]['payload']);
    }

    #[Test]
    public function the_grid_reads_its_defaults_and_says_a_run_of_days_with_a_dash(): void
    {
        $this->assertCount(16, DateTimeField::times(null));
        $this->assertSame(['09:00', '10:30', '12:00'], DateTimeField::times(['time-step' => 90, 'time-from' => '09:00', 'time-to' => '13:30']));
        $this->assertSame('Mon, Wed, Fri', DateTimeField::days([1, 3, 5], 'en'));
        $this->assertSame('Mon, Tue, Thu–Sun', DateTimeField::days([1, 2, 4, 5, 6, 7], 'en'));
        $this->assertSame('09:00, 10:00, 11:00', DateTimeField::describe(['time-step' => 60, 'time-from' => '09:00', 'time-to' => '12:00']));
    }

    /** A form of one name and a meeting on weekdays, 09:00–17:00 every half an hour. */
    private function meeting(): Form
    {
        return $this->form('contact', [
            ['name' => 'name', 'type' => FieldType::Text],
            ['name' => 'meeting', 'type' => FieldType::DateTime, 'title' => ['en' => 'Meeting'], 'is_required' => true, 'options' => [
                'time-step' => 30, 'time-from' => '09:00', 'time-to' => '17:00', 'weekdays' => [1, 2, 3, 4, 5],
            ]],
        ]);
    }

    private function page(string $blade): string
    {
        $this->app->forgetScopedInstances();

        Route::middleware('web')->get('/page', static fn (): string => Blade::render($blade));

        return $this->get('/page')->assertOk()->getContent() ?: '';
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        return WebxServer::tool(new RegistryTool($this->app->make(ToolRegistry::class)->tool('inbox_'.$tool)), $arguments);
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
