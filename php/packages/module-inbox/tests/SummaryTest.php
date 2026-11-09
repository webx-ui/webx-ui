<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Widgets\Widgets;

/**
 * The summary of a form (FORM_STEPS §7): a row of type `summary` asks nothing and shows the answers
 * above it. The widgets draw it (`<x-webx-form.summary>`, summary.js); here — what the inbox makes
 * of the row: the page, the intake that keeps nothing of it, the panel, MCP and a rename.
 */
final class SummaryTest extends TestCase
{
    #[Test]
    public function a_summary_is_printed_in_its_step_hidden_with_the_fields_it_names(): void
    {
        $this->meeting();

        $html = $this->page('<x-webx-inbox::form slug="meeting" />');

        // In the step of the contacts, over the e-mail; hidden for the page without JavaScript.
        $this->assertMatchesRegularExpression('~<section id="[^"]+" class="webx-form-summary webx-inbox-form__summary" data-webx-summary [^>]*data-webx-summary-fields="\[&quot;fields.purpose&quot;,&quot;fields.day&quot;\]"[^>]*hidden>~', $html);
        $this->assertStringContainsString('role="heading" aria-level="4">Your request</p>', $html);
        $this->assertStringContainsString('<p class="webx-form-summary__hint">Check it before sending.</p>', $html);
        $this->assertGreaterThan(strpos($html, 'data-webx-step="step-contacts"'), strpos($html, 'data-webx-summary'));
        $this->assertLessThan(strpos($html, 'data-webx-field="fields.email"'), strpos($html, 'data-webx-summary'));
        // Not a field: no control, no name the form would send.
        $this->assertStringNotContainsString('fields[summary]', $html);
        $this->assertStringNotContainsString('data-webx-field="fields.summary"', $html);

        app(Widgets::class)->flush();
        Blade::render('<x-webx-inbox::form slug="meeting" />');
        $this->assertContains('summary', app(Widgets::class)->claimed());
    }

    #[Test]
    public function a_summary_naming_no_field_shows_every_one_above_and_one_whose_fields_are_gone_is_not_printed(): void
    {
        $form = $this->meeting();
        $summary = Field::query()->where('name', 'summary')->sole();

        $summary->update(['options' => []]);
        $html = $this->page('<x-webx-inbox::form slug="meeting" />');
        $this->assertStringContainsString('data-webx-summary ', $html);
        $this->assertStringNotContainsString('data-webx-summary-fields', $html);

        $summary->update(['options' => ['fields' => ['gone']]]);
        $html = $this->page('<x-webx-inbox::form slug="meeting" />');
        $this->assertStringNotContainsString('data-webx-summary', $html);

        // A flat form has every answer in sight: a summary in it is not printed.
        $this->form('flat', [
            ['name' => 'name', 'type' => FieldType::Text],
            ['name' => 'summary', 'type' => FieldType::Summary, 'title' => ['en' => 'Your request']],
        ]);
        $flat = $this->page('<x-webx-inbox::form slug="flat" />');
        $this->assertStringNotContainsString('data-webx-summary', $flat);
        $this->assertStringContainsString('name="fields[name]"', $flat);
        $this->assertSame(1, $form->refresh()->fields()->where('type', FieldType::Summary->value)->count());
    }

    #[Test]
    public function the_intake_keeps_nothing_of_a_summary(): void
    {
        $this->meeting();

        $this->postJson($this->intake('meeting'), ['fields' => [
            'purpose' => 'agency',
            'day' => 'Monday',
            'summary' => 'Agency meeting, Monday',
            'email' => 'visitor@example.test',
        ]])->assertOk();

        $submission = Submission::query()->sole();
        $this->assertSame(['purpose', 'day', 'email'], $submission->values->pluck('name')->all());

        // Nor does the door that checks a step ask anything of it.
        $this->postJson($this->intake('meeting').'/check', ['webx_step' => 'step-contacts', 'fields' => ['purpose' => 'direct', 'email' => 'visitor@example.test']])
            ->assertNoContent();
    }

    #[Test]
    public function the_panel_keeps_what_a_summary_has_and_a_rename_carries_into_it(): void
    {
        $form = $this->meeting();
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api("forms/{$form->id}/fields"), [
            'name' => 'recap',
            'type' => 'summary',
            'title' => ['en' => 'Recap'],
            'is_required' => true,
            'in_table' => true,
            'is_fullsize' => false,
            'options' => ['fields' => ['purpose', ' ', 'purpose', 'email'], 'choices' => [['value' => 'a']], 'when' => ['purpose' => ['agency']]],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'summary')
            ->assertJsonPath('data.is_required', false)
            ->assertJsonPath('data.in_table', false)
            ->assertJsonPath('data.is_fullsize', true)
            ->assertJsonPath('data.options', ['fields' => ['purpose', 'email']]);

        $purpose = Field::query()->where('name', 'purpose')->sole();
        $this->actingAs($editor, 'cms')->putJson($this->api("fields/{$purpose->id}"), [
            'name' => 'goal',
            'type' => 'radio',
            'title' => ['en' => 'Purpose'],
            'options' => $purpose->options,
        ])->assertOk();

        $this->assertSame(['goal', 'day'], Field::query()->where('name', 'summary')->sole()->option('fields'));
        $this->assertSame(['goal', 'email'], Field::query()->where('name', 'recap')->sole()->option('fields'));
    }

    #[Test]
    public function an_agent_writes_a_summary_and_a_rename_in_the_same_call_carries_into_it(): void
    {
        $fields = [
            ['type' => 'radio', 'name' => 'purpose', 'title' => ['en' => 'Purpose'], 'options' => ['choices' => [['value' => 'agency', 'label' => ['en' => 'Agency meeting']]]]],
            ['type' => 'step', 'name' => 'step-contacts', 'title' => ['en' => 'Your details']],
            ['type' => 'summary', 'name' => 'summary', 'title' => ['en' => 'Your request'], 'is_required' => true, 'options' => ['fields' => ['purpose']]],
            ['type' => 'email', 'name' => 'email', 'title' => ['en' => 'E-mail']],
        ];

        $plan = $this->content($this->agent('form_save', ['slug' => 'agent', 'title' => 'Agent', 'fields' => $fields, 'dry_run' => true])->assertOk());
        $this->assertTrue($plan['dry_run']);
        $this->assertSame(0, Form::query()->count());

        $saved = $this->content($this->agent('form_save', ['slug' => 'agent', 'title' => 'Agent', 'fields' => $fields])->assertOk());
        $this->assertSame('summary', $saved['fields'][2]['type']);
        $this->assertFalse($saved['fields'][2]['is_required']);
        $this->assertEquals(['fields' => ['purpose']], $saved['fields'][2]['options']);

        $this->agent('form_save', ['form' => 'agent', 'fields' => [['id' => Field::query()->where('name', 'purpose')->value('id'), 'name' => 'goal']]])->assertOk();
        $this->assertSame(['goal'], Field::query()->where('name', 'summary')->sole()->option('fields'));
    }

    /**
     * The purpose, the time of an agency meeting, then the contacts with a summary of the two over
     * the e-mail.
     */
    private function meeting(): Form
    {
        return $this->form('meeting', [
            ['name' => 'purpose', 'type' => FieldType::Radio, 'is_required' => true, 'options' => ['choices' => [
                ['value' => 'direct', 'label' => ['en' => 'Direct inquiry']],
                ['value' => 'agency', 'label' => ['en' => 'Agency meeting']],
            ]]],
            ['name' => 'step-time', 'type' => FieldType::Step, 'title' => ['en' => 'Choose a meeting time'], 'options' => ['when' => ['purpose' => ['agency']]]],
            ['name' => 'day', 'type' => FieldType::Text, 'is_required' => true],
            ['name' => 'step-contacts', 'type' => FieldType::Step, 'title' => ['en' => 'Your details']],
            ['name' => 'summary', 'type' => FieldType::Summary, 'title' => ['en' => 'Your request'], 'help' => ['en' => 'Check it before sending.'], 'options' => ['fields' => ['purpose', 'day']]],
            ['name' => 'email', 'type' => FieldType::Email, 'is_required' => true],
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
