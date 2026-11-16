<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\DateTimeField;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;

/**
 * The options of a «Date and time» (FORM_STEPS §6.7): the visitor's zone, the days ahead, the times
 * skipped — each checked by the intake as the page offers it — and the panel of slots and the first
 * day chosen, which are the page's alone. Every one is off by default.
 */
final class DateTimeOptionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The site's zone, two hours ahead of UTC in winter.
        $app['config']->set('app.timezone', 'Europe/Kyiv');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday 10 December 2026, 10:10 in Kyiv, 03:10 in New York, 17:10 in Tokyo.
        Carbon::setTestNow(Carbon::parse('2026-12-10 08:10:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function a_field_of_the_visitors_zone_keeps_the_zone_the_browser_sent_and_the_moment_in_utc(): void
    {
        $this->meeting(['timezone' => 'visitor']);

        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-14', 'time' => '10:30', 'timezone' => 'America/New_York']]])->assertOk();

        $value = Submission::query()->sole()->values->sole();
        $this->assertSame('12/14/2026, 10:30 AM (America/New_York)', $value->value);
        $this->assertSame(['date' => '2026-12-14', 'time' => '10:30', 'timezone' => 'America/New_York', 'at' => '2026-12-14T15:30:00Z'], $value->payload);
    }

    #[Test]
    public function a_zone_that_does_not_exist_is_refused_and_none_at_all_is_the_sites(): void
    {
        $this->meeting(['timezone' => 'visitor']);

        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-14', 'time' => '10:30', 'timezone' => 'Mars/Olympus']]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['fields.meeting' => ['Your time zone was not recognised. Reload the page and choose the time again.']]);

        // A page without JavaScript sends the site's zone as it was printed, or nothing.
        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-14', 'time' => '10:30']]])->assertOk();
        $this->assertSame('Europe/Kyiv', Submission::query()->sole()->values->sole()->payload['timezone']);
    }

    #[Test]
    public function a_field_of_the_sites_zone_ignores_a_zone_sent_with_the_answer(): void
    {
        $this->meeting();

        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-14', 'time' => '10:30', 'timezone' => 'America/New_York']]])->assertOk();

        $this->assertSame('2026-12-14T08:30:00Z', Submission::query()->sole()->values->sole()->payload['at']);
    }

    #[Test]
    public function the_past_and_the_day_of_the_week_are_counted_in_the_visitors_zone(): void
    {
        $this->meeting(['timezone' => 'visitor']);
        $refused = fn (array $answer, string $words) => $this->postJson($this->intake(), ['fields' => ['meeting' => $answer]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['fields.meeting' => [$words]]);

        // 17:10 in Tokyo: 16:30 of today is gone there, though it is still morning in Kyiv.
        $refused(['date' => '2026-12-10', 'time' => '16:30', 'timezone' => 'Asia/Tokyo'], 'This time has already passed. Choose a later one.');
        // 03:10 in New York: 09:00 of today is to come there, though it is gone in Kyiv.
        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-10', 'time' => '09:00', 'timezone' => 'America/New_York']]])->assertOk();
        // A Saturday is a Saturday in any zone.
        $refused(['date' => '2026-12-12', 'time' => '10:30', 'timezone' => 'America/New_York'], 'Choose a weekday: Mon–Fri.');
    }

    #[Test]
    public function lead_days_refuse_the_days_before_the_first_that_may_be_chosen(): void
    {
        $this->meeting(['lead-days' => 1]);

        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-10', 'time' => '16:30']]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['fields.meeting' => ['Choose a day from December 11, 2026 on.']]);

        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-11', 'time' => '09:00']]])->assertOk();
        $this->assertSame(1, Submission::query()->count());
    }

    #[Test]
    public function a_time_skipped_is_neither_offered_nor_taken(): void
    {
        $this->meeting(['time-skip' => ['13:30', '9:00']]);

        $this->assertSame(['09:30', '10:00'], array_slice(DateTimeField::times(['time-skip' => ['13:30', '9:00']]), 0, 2));
        $this->assertNotContains('13:30', DateTimeField::times(['time-skip' => '13:30']));

        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-14', 'time' => '13:30']]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['fields.meeting' => ['Choose one of the times offered.']]);
        $this->postJson($this->intake(), ['fields' => ['meeting' => ['date' => '2026-12-14', 'time' => '14:00']]])->assertOk();

        $html = $this->page('<x-webx-inbox::form slug="contact" />');
        $this->assertStringNotContainsString('<option value="13:30"', $html);
        $this->assertStringContainsString('<option value="14:00"', $html);
    }

    #[Test]
    public function the_panel_keeps_the_options_and_refuses_wrong_ones(): void
    {
        $form = $this->form('setup', [['name' => 'name', 'type' => FieldType::Text]]);
        $editor = $this->editor();
        $save = fn (array $options, string $name = 'visit') => $this->actingAs($editor, 'cms')->postJson($this->api("forms/{$form->id}/fields"), [
            'name' => $name,
            'type' => 'datetime',
            'title' => ['en' => 'Visit'],
            'options' => $options,
        ]);

        $save(['lead-days' => 400])->assertStatus(422)->assertJsonPath('errors', ['options.lead-days' => ['Days ahead is a whole number from 0 to 365.']]);
        $save(['time-skip' => ['25:00']])->assertStatus(422)->assertJsonValidationErrors(['options.time-skip']);
        // Every time of the day skipped leaves nothing to choose.
        $save(['time-from' => '09:00', 'time-to' => '10:00', 'time-skip' => ['09:00', '09:30']])->assertStatus(422)->assertJsonValidationErrors(['options.time-skip']);
        $save(['timezone' => 'Mars/Olympus'])->assertStatus(422)->assertJsonPath('errors', ['options.timezone' => ['The time zone is the site’s or the visitor’s.']]);

        $save(['timezone' => 'visitor', 'lead-days' => '1', 'time-skip' => ['13:30', '12:00'], 'slots' => 'panel', 'first-day' => true])
            ->assertCreated()
            ->assertJsonPath('data.options', ['timezone' => 'visitor', 'lead-days' => 1, 'time-skip' => ['12:00', '13:30'], 'slots' => 'panel', 'first-day' => true]);

        // Off is not written down: a field saved before the options reads the same as one with them off.
        $save(['timezone' => '', 'lead-days' => 0, 'time-skip' => [], 'slots' => 'list', 'first-day' => false], 'call')
            ->assertCreated()
            ->assertJsonMissingPath('data.options.timezone')
            ->assertJsonMissingPath('data.options.lead-days')
            ->assertJsonMissingPath('data.options.first-day');
    }

    #[Test]
    public function the_page_prints_the_options_for_the_script_and_the_zone_for_a_page_without_it(): void
    {
        $this->meeting(['timezone' => 'visitor', 'lead-days' => 1, 'slots' => 'panel', 'first-day' => true]);

        $html = $this->page('<x-webx-inbox::form slug="contact" />');

        // Without JavaScript: the site's zone goes with the answer, the first day is tomorrow.
        $this->assertMatchesRegularExpression('~<input type="hidden" name="fields\[meeting\]\[timezone\]" value="Europe/Kyiv" data-webx-timezone\s*>~', $html);
        $this->assertStringContainsString('min="2026-12-11"', $html);
        foreach (['&quot;lead&quot;:1', '&quot;visitor&quot;:true', '&quot;panel&quot;:true', '&quot;first&quot;:true', '&quot;zone&quot;:&quot;Time zone: :zone&quot;'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
    }

    #[Test]
    public function a_field_without_the_options_prints_what_it_always_did(): void
    {
        $this->meeting();

        $html = $this->page('<x-webx-inbox::form slug="contact" />');

        $this->assertStringNotContainsString('[timezone]', $html);
        $this->assertStringContainsString('min="2026-12-10"', $html);
        $this->assertStringContainsString('&quot;visitor&quot;:false', $html);
        $this->assertStringContainsString('&quot;panel&quot;:false', $html);
    }

    /**
     * A form of one name and a meeting on weekdays, 09:00–17:00 every half an hour.
     *
     * @param  array<string, mixed>  $options
     */
    private function meeting(array $options = []): Form
    {
        return $this->form('contact', [
            ['name' => 'name', 'type' => FieldType::Text],
            ['name' => 'meeting', 'type' => FieldType::DateTime, 'title' => ['en' => 'Meeting'], 'is_required' => true, 'options' => [
                'time-step' => 30, 'time-from' => '09:00', 'time-to' => '17:00', 'weekdays' => [1, 2, 3, 4, 5], ...$options,
            ]],
        ]);
    }

    private function page(string $blade): string
    {
        $this->app->forgetScopedInstances();

        Route::middleware('web')->get('/page', static fn (): string => Blade::render($blade));

        return $this->get('/page')->assertOk()->getContent() ?: '';
    }
}
