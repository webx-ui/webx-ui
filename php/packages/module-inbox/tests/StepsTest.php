<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\AuditServiceProvider;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Probes\ProbeSet;
use WebxUi\Audit\Probes\SiteClient;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Fields\Steps;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Widgets\Widgets;

/**
 * Steps of an inbox form (FORM_STEPS §3): a row of type `step` starts one. The widgets draw them
 * (`<x-webx-form steps>`, `<x-webx-form.step>`); here — what the inbox makes of the row: the page,
 * the intake, the door that checks a step, the panel, MCP and the audit.
 */
final class StepsTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuditServiceProvider::class];
    }

    #[Test]
    public function a_flat_form_prints_the_same_html_as_before_there_were_steps(): void
    {
        $this->flat();

        $html = self::stable($this->page('<x-webx-inbox::form slug="flat" />'));
        $snapshot = __DIR__.'/Fixtures/flat-form.html';

        if (! is_file($snapshot)) {
            file_put_contents($snapshot, $html);
            $this->markTestIncomplete('The snapshot was written.');
        }

        // Written from the package as it was before this stage: eleven types, a condition, cards.
        $this->assertSame(file_get_contents($snapshot), $html);

        app(Widgets::class)->flush();
        Blade::render('<x-webx-inbox::form slug="flat" />');
        $this->assertNotContains('steps', app(Widgets::class)->claimed());
    }

    #[Test]
    public function a_form_of_steps_prints_a_fieldset_per_step_and_the_door_that_checks_one(): void
    {
        $this->stepped();

        $html = $this->page('<x-webx-inbox::form slug="meeting" />');

        $this->assertStringContainsString('class="webx-form webx-form--steps webx-inbox-form"', $html);
        $this->assertStringContainsString('data-webx-check="'.url($this->intake('meeting')).'/check"', $html);

        // The fields before the first row are a step called by the form's title.
        $this->assertStringContainsString('data-webx-step="'.Steps::FIRST.'"', $html);
        $this->assertMatchesRegularExpression('~<span class="webx-form-step__title"[^>]*>Meeting</span>~', $html);
        $this->assertMatchesRegularExpression('~data-webx-step="step-time"[^>]*data-webx-step-label="Time"[^>]*data-webx-when="\{&quot;fields.purpose&quot;:\[&quot;agency&quot;\]\}"~', $html);
        $this->assertStringContainsString('If chosen: Agency meeting', $html);
        $this->assertStringContainsString('>Pick a time<', $html);
        $this->assertStringContainsString('data-webx-step-next>Choose the time</button>', $html);
        $this->assertStringContainsString('data-webx-step="step-contacts"', $html);

        // Each field in its step, in order.
        $this->assertLessThan(strpos($html, 'data-webx-step="step-time"'), strpos($html, 'data-webx-field="fields.purpose"'));
        $this->assertGreaterThan(strpos($html, 'data-webx-step="step-time"'), strpos($html, 'data-webx-field="fields.day"'));
        $this->assertGreaterThan(strpos($html, 'data-webx-step="step-contacts"'), strpos($html, 'data-webx-field="fields.email"'));

        // A step that is off takes its field with it; an empty step is not printed.
        $this->assertStringNotContainsString('step-later', $html);
        $this->assertStringNotContainsString('fields[later]', $html);
        $this->assertStringNotContainsString('step-nothing', $html);

        // The day of a step that has a condition is not `required` to a page without JavaScript.
        $this->assertDoesNotMatchRegularExpression('~name="fields\[day\]"[^>]*\srequired~', $html);

        app(Widgets::class)->flush();
        Blade::render('<x-webx-inbox::form slug="meeting" />');
        $this->assertContains('steps', app(Widgets::class)->claimed());
    }

    #[Test]
    public function the_intake_reads_a_step_as_nothing_and_drops_what_a_hidden_step_holds(): void
    {
        $this->stepped();

        // Without JavaScript every step is on the page: the day is sent though the step is hidden.
        $this->postJson($this->intake('meeting'), ['fields' => [
            'purpose' => 'direct',
            'day' => 'Monday',
            'later' => 'never asked',
            'email' => 'visitor@example.test',
        ]])->assertOk();

        $submission = Submission::query()->sole();

        $this->assertSame(['purpose', 'email'], $submission->values->pluck('name')->all());

        // A required field of a shown step is required.
        $this->postJson($this->intake('meeting'), ['fields' => ['purpose' => 'agency', 'email' => 'visitor@example.test']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.day']);
    }

    #[Test]
    public function the_door_that_checks_a_step_checks_the_steps_up_to_it_and_takes_nothing(): void
    {
        $this->stepped();
        $check = $this->intake('meeting').'/check';

        // Only the steps up to the one named: the e-mail of the last step is not asked for yet.
        $this->postJson($check, ['webx_step' => Steps::FIRST, 'fields' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.purpose'])
            ->assertJsonMissingValidationErrors(['fields.email', 'fields.day']);

        $this->postJson($check, ['webx_step' => Steps::FIRST, 'fields' => ['purpose' => 'agency']])->assertNoContent();

        // The time step under its condition: the day is required when it is shown, and not otherwise.
        $this->postJson($check, ['webx_step' => 'step-time', 'fields' => ['purpose' => 'agency']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.day']);
        $this->postJson($check, ['webx_step' => 'step-time', 'fields' => ['purpose' => 'direct']])->assertNoContent();

        // The server's own rules, on «Next» and not three screens later.
        $this->postJson($check, ['webx_step' => 'step-contacts', 'fields' => ['purpose' => 'direct', 'email' => 'a@b']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.email']);

        // A step the form does not have — a page printed before the form changed.
        $this->postJson($check, ['webx_step' => 'step-gone', 'fields' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['webx_step']);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function checking_a_step_is_not_a_submission_to_the_limit_and_asks_for_no_file(): void
    {
        config()->set('webx-inbox.antispam.throttle', 1);
        $form = $this->stepped();
        $this->field($form, ['name' => 'brief', 'type' => FieldType::File, 'is_required' => true, 'position' => 1, 'options' => ['extensions' => ['pdf']]]);
        $check = $this->intake('meeting').'/check';

        // A required file of the step stayed in the browser, which measured it.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson($check, ['webx_step' => Steps::FIRST, 'fields' => ['purpose' => 'direct']])->assertNoContent();
        }

        // Three checks later the one submission the limit allows still goes through.
        $this->post($this->intake('meeting'), [
            'fields' => ['purpose' => 'direct', 'email' => 'visitor@example.test', 'brief' => UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk();

        // And somebody else's page is not answered.
        $this->postJson($check, ['webx_step' => Steps::FIRST, 'fields' => ['purpose' => 'direct']], ['Origin' => 'https://elsewhere.example'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['form']);
    }

    #[Test]
    public function a_step_row_keeps_only_what_a_step_has_and_no_more_than_ten_fit(): void
    {
        $form = $this->form('long', [['name' => 'name', 'type' => FieldType::Text]]);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api("forms/{$form->id}/fields"), [
            'name' => 'step-first',
            'type' => 'step',
            'title' => ['en' => 'About you'],
            'placeholder' => ['en' => 'You'],
            'is_required' => true,
            'in_table' => true,
            'is_fullsize' => false,
            'options' => ['next-text' => ['en' => 'On', 'ru' => ' '], 'choices' => [['value' => 'a']], 'rows' => 4],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'step')
            ->assertJsonPath('data.is_required', false)
            ->assertJsonPath('data.in_table', false)
            ->assertJsonPath('data.is_fullsize', true)
            ->assertJsonPath('data.options', ['next-text' => ['en' => 'On']]);

        // A condition on a text field is refused for a step as for a field.
        $this->actingAs($editor, 'cms')->postJson($this->api("forms/{$form->id}/fields"), [
            'type' => 'step', 'title' => ['en' => 'Two'], 'options' => ['when' => ['name' => ['x']]],
        ])->assertStatus(422)->assertJsonValidationErrors(['options.when']);

        // «+ Step» at the end is allowed (the list says it is empty); the eleventh step is not — the
        // field above the first row being step 1, the row step 2.
        for ($i = 3; $i <= 10; $i++) {
            $this->actingAs($editor, 'cms')->postJson($this->api("forms/{$form->id}/fields"), ['type' => 'step', 'title' => ['en' => "Step {$i}"]])->assertCreated();
        }

        $this->actingAs($editor, 'cms')->postJson($this->api("forms/{$form->id}/fields"), ['type' => 'step', 'title' => ['en' => 'Eleven']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function a_form_of_steps_is_written_by_an_agent_and_read_back_with_its_steps(): void
    {
        $fields = [
            ['type' => 'step', 'name' => 'step-purpose', 'title' => ['en' => 'Purpose of contact']],
            ['type' => 'radio', 'name' => 'purpose', 'title' => ['en' => 'How can we help?'], 'is_required' => true, 'options' => ['choices' => [
                ['value' => 'direct', 'label' => ['en' => 'Direct inquiry']],
                ['value' => 'agency', 'label' => ['en' => 'Agency meeting']],
            ]]],
            ['type' => 'step', 'name' => 'step-meeting', 'title' => ['en' => 'Choose a meeting time'], 'options' => ['when' => ['purpose' => ['agency']], 'next-text' => ['en' => 'Continue']]],
            ['type' => 'date', 'name' => 'day', 'title' => ['en' => 'Day'], 'is_required' => true],
            ['type' => 'step', 'name' => 'step-contacts', 'title' => ['en' => 'Your details'], 'help' => ['en' => 'How we reach you']],
            ['type' => 'email', 'name' => 'email', 'title' => ['en' => 'E-mail'], 'is_required' => true],
        ];

        // A dry run says the steps the form would have and writes nothing.
        $plan = $this->content($this->agent('form_save', ['slug' => 'contact-steps', 'title' => 'Contact us', 'fields' => $fields, 'dry_run' => true])->assertOk());
        $this->assertSame([
            ['name' => 'step-purpose', 'fields' => ['purpose']],
            ['name' => 'step-meeting', 'fields' => ['day']],
            ['name' => 'step-contacts', 'fields' => ['email']],
        ], $plan['steps']);
        $this->assertSame(0, Form::query()->count());

        $saved = $this->content($this->agent('form_save', ['slug' => 'contact-steps', 'title' => 'Contact us', 'fields' => $fields])->assertOk());
        $this->assertSame([
            ['name' => 'step-purpose', 'title' => 'Purpose of contact', 'fields' => ['purpose']],
            ['name' => 'step-meeting', 'title' => 'Choose a meeting time', 'fields' => ['day']],
            ['name' => 'step-contacts', 'title' => 'Your details', 'fields' => ['email']],
        ], $saved['steps']);
        $this->assertSame('step', $saved['fields'][2]['type']);
        $this->assertEquals(['when' => ['purpose' => ['agency']], 'next-text' => ['en' => 'Continue']], $saved['fields'][2]['options']);

        // An empty step is refused for the whole call, and so is a condition naming a field below.
        $this->agent('form_save', ['form' => 'contact-steps', 'fields' => [['type' => 'step', 'name' => 'step-end', 'title' => 'The end']]])
            ->assertHasErrors(['step-end has no field after it']);
        $this->assertSame(6, Field::query()->count());

        // `order` brings a flat form into steps: the new row goes where the call says.
        $this->agent('form_save', ['form' => 'contact-steps', 'fields' => [['type' => 'text', 'name' => 'name', 'title' => 'Name']], 'order' => ['step-purpose', 'purpose', 'step-contacts', 'name']])->assertOk();
        $read = $this->content($this->agent('form_get', ['form' => 'contact-steps'])->assertOk());
        $this->assertSame(['step-purpose', 'purpose', 'step-contacts', 'name', 'step-meeting', 'day', 'email'], array_column($read['fields'], 'name'));
        $this->assertSame(['purpose', 'name', 'day', 'email'], array_merge(...array_column($read['steps'], 'fields')));

        $this->agent('form_save', ['form' => 'contact-steps', 'order' => ['nothing']])
            ->assertHasErrors(['`order` names fields the form would not have: nothing']);

        // A flat form says nothing of steps.
        $this->form();
        $this->assertArrayNotHasKey('steps', $this->content($this->agent('form_get', ['form' => 'contact'])->assertOk()));
    }

    #[Test]
    public function a_view_of_the_site_that_prints_no_steps_gets_the_whole_form_and_is_named_by_the_audit(): void
    {
        $this->stepped();
        $this->assertSame([], $this->findings(), 'The package\'s own view prints the steps.');

        $this->app['view']->prependNamespace('webx-inbox', __DIR__.'/Fixtures/flat-view');
        $this->app['view']->getFinder()->flush();

        $html = $this->page('<x-webx-inbox::form slug="meeting" />');

        // Every field on one page, as without JavaScript; no step was printed.
        $this->assertStringNotContainsString('data-webx-step', $html);
        foreach (['purpose', 'day', 'email'] as $key) {
            $this->assertStringContainsString('name="fields['.$key.']"', $html);
        }

        $findings = $this->findings();
        $this->assertCount(1, $findings);
        $this->assertSame('warning', $findings[0]->severity);
        $this->assertSame((string) Form::query()->where('slug', 'meeting')->value('id'), $findings[0]->key);

        foreach (['title', 'found', 'why', 'fix'] as $text) {
            $key = 'webx-inbox::checks.inbox.steps_in_old_view.'.$text;
            $this->assertNotSame($key, __($key), $text);
        }
    }

    /**
     * A form of three steps and two that are not printed: the purpose before the first row, the
     * time only for an agency meeting, the contacts; a step switched off and an empty one.
     */
    private function stepped(): Form
    {
        return $this->form('meeting', [
            ['name' => 'purpose', 'type' => FieldType::Radio, 'is_required' => true, 'options' => ['choices' => [
                ['value' => 'direct', 'label' => ['en' => 'Direct inquiry']],
                ['value' => 'agency', 'label' => ['en' => 'Agency meeting']],
            ]]],
            ['name' => 'step-time', 'type' => FieldType::Step, 'title' => ['en' => 'Choose a meeting time'], 'placeholder' => ['en' => 'Time'], 'help' => ['en' => 'Pick a time'], 'options' => [
                'when' => ['purpose' => ['agency']],
                'next-text' => ['en' => 'Choose the time'],
            ]],
            ['name' => 'day', 'type' => FieldType::Text, 'is_required' => true],
            ['name' => 'step-later', 'type' => FieldType::Step, 'is_enabled' => false, 'title' => ['en' => 'Later']],
            ['name' => 'later', 'type' => FieldType::Text, 'is_required' => true],
            ['name' => 'step-nothing', 'type' => FieldType::Step, 'title' => ['en' => 'Nothing']],
            ['name' => 'step-contacts', 'type' => FieldType::Step, 'title' => ['en' => 'Your details']],
            ['name' => 'email', 'type' => FieldType::Email, 'is_required' => true],
        ]);
    }

    /** Eleven types, a condition and cards: a form of everything that came before the steps. */
    private function flat(): void
    {
        $this->form('flat', [
            ['name' => 'line', 'type' => FieldType::Text, 'is_required' => true, 'options' => ['maxlength' => 40]],
            ['name' => 'mail', 'type' => FieldType::Email, 'is_fullsize' => false],
            ['name' => 'phone', 'type' => FieldType::Tel, 'is_fullsize' => false, 'options' => ['pattern' => '[0-9 +()-]{6,}']],
            ['name' => 'letter', 'type' => FieldType::Textarea, 'help' => ['en' => 'A few words'], 'options' => ['rows' => 8]],
            ['name' => 'plan', 'type' => FieldType::Select, 'placeholder' => ['en' => 'Pick one'], 'options' => ['choices' => [
                ['value' => 'basic', 'label' => ['en' => 'Basic']],
                ['value' => 'pro', 'label' => ['en' => 'Pro']],
            ]]],
            ['name' => 'reach', 'type' => FieldType::Radio, 'is_required' => true, 'options' => ['view' => 'cards', 'columns' => 2, 'choices' => [
                ['value' => 'call', 'label' => ['en' => 'By phone'], 'description' => ['en' => 'We call you back']],
                ['value' => 'mail', 'label' => ['en' => 'By mail']],
            ]]],
            ['name' => 'extras', 'type' => FieldType::Checkbox, 'options' => ['when' => ['reach' => ['call']], 'choices' => [
                ['value' => 'ssl', 'label' => ['en' => 'SSL']],
            ]]],
            ['name' => 'terms', 'type' => FieldType::Consent, 'is_required' => true, 'options' => [
                'text' => ['en' => 'I agree to the <a href="/privacy">privacy policy</a>'],
            ]],
            ['name' => 'when', 'type' => FieldType::Date, 'options' => ['min' => '2026-01-01', 'unless' => ['terms' => ['1']]]],
            ['name' => 'brief', 'type' => FieldType::File, 'options' => ['extensions' => ['pdf']]],
            ['name' => 'product', 'type' => FieldType::Hidden],
        ]);
    }

    /** The page without what changes from one rendering to the next: the moment it was printed. */
    private static function stable(string $html): string
    {
        return (string) preg_replace('~(name="webx_ts" value=")[^"]*~', '$1…', $html);
    }

    private function page(string $blade): string
    {
        $this->app->forgetScopedInstances();

        Route::middleware('web')->get('/page', static fn (): string => Blade::render($blade));

        return $this->get('/page')->assertOk()->getContent() ?: '';
    }

    /**
     * @return list<Finding>
     */
    private function findings(): array
    {
        $check = $this->app->make(AuditChecks::class)->get('inbox.steps_in_old_view');
        $this->assertNotNull($check);

        $context = new AuditContext(new AuditRun, new HostClassifier(['example.test']), $this->app->make(SiteClient::class), new ProbeSet, $this->app->make('config'));

        return array_values(iterator_to_array($check->run($context), false));
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
