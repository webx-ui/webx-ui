<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\Conditions;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Submissions\Intake;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A field shown only for some answers above it (FORM_STEPS §4): the server decides, so a hidden
 * field is not checked, not kept and not in the hash — with JavaScript and without it — and the
 * panel and `inbox_form_save` refuse a condition that names what it may not.
 */
final class ConditionsTest extends TestCase
{
    private const array MEETING = ['purpose' => ['agency', 'pre-register']];

    #[Test]
    public function a_hidden_field_is_not_checked_and_its_answer_is_not_kept(): void
    {
        $this->meetingForm();

        $this->postJson($this->intake(), ['fields' => [
            'name' => 'Alex',
            'purpose' => 'direct',
            // Sent anyway — a page without JavaScript shows every field.
            'agency' => 'Example agency',
        ]])->assertOk();

        $submission = Submission::query()->sole();

        $this->assertSame('Direct inquiry', $submission->value('purpose')?->value);
        $this->assertNull($submission->value('agency'));
    }

    #[Test]
    public function a_shown_field_is_checked_by_its_own_rules(): void
    {
        $this->meetingForm();

        $this->postJson($this->intake(), ['fields' => ['name' => 'Alex', 'purpose' => 'agency']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.agency'])
            ->assertJsonMissingValidationErrors(['fields.name']);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Alex', 'purpose' => 'pre-register', 'agency' => 'Example agency']])
            ->assertOk();

        $this->assertSame('Example agency', Submission::query()->sole()->value('agency')?->value);
    }

    #[Test]
    public function without_javascript_the_same_errors_come_back_under_the_same_fields(): void
    {
        $this->meetingForm();

        $page = ['referer' => 'http://localhost/contact'];

        $this->post($this->intake(), ['webx_form' => 'contact', 'fields' => ['name' => 'Alex', 'purpose' => 'agency']], $page)
            ->assertRedirect('http://localhost/contact')
            ->assertSessionHasErrors(['fields.agency'])
            ->assertSessionDoesntHaveErrors(['fields.name']);

        $this->post($this->intake(), ['webx_form' => 'contact', 'fields' => ['name' => 'Alex', 'purpose' => 'direct', 'agency' => '']], $page)
            ->assertSessionHasNoErrors();

        $this->assertNull(Submission::query()->sole()->value('agency'));
    }

    #[Test]
    public function a_hidden_answer_is_not_in_the_hash(): void
    {
        $form = $this->meetingForm();
        $intake = $this->app->make(Intake::class);

        $this->assertSame(
            $intake->hash($form, ['name' => 'Alex', 'purpose' => 'direct']),
            $intake->hash($form, ['name' => 'Alex', 'purpose' => 'direct', 'agency' => 'Example agency']),
        );
        $this->assertNotSame(
            $intake->hash($form, ['name' => 'Alex', 'purpose' => 'agency', 'agency' => 'One']),
            $intake->hash($form, ['name' => 'Alex', 'purpose' => 'agency', 'agency' => 'Two']),
        );

        // So the same request sent twice, once with a stray answer, is one submission.
        $this->postJson($this->intake(), ['fields' => ['name' => 'Alex', 'purpose' => 'direct']])->assertOk();
        $this->postJson($this->intake(), ['fields' => ['name' => 'Alex', 'purpose' => 'direct', 'agency' => 'Example agency']])->assertOk();

        $this->assertSame(1, Submission::query()->count());
    }

    #[Test]
    public function a_form_without_conditions_hashes_as_it_always_did(): void
    {
        $form = $this->form();
        $values = ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello'];
        $subject = ['email' => 'ada@example.test', 'message' => 'Hello', 'name' => 'Ada'];

        $this->assertSame(
            md5($form->getKey().'|'.json_encode($subject, JSON_UNESCAPED_UNICODE)),
            $this->app->make(Intake::class)->hash($form, $values),
        );
    }

    #[Test]
    public function an_answer_in_a_hidden_field_does_not_reach_the_fields_below(): void
    {
        $this->form('contact', [
            ['name' => 'purpose', 'type' => FieldType::Radio, 'is_required' => true, 'options' => ['choices' => self::choices()]],
            ['name' => 'kind', 'type' => FieldType::Select, 'options' => ['when' => ['purpose' => ['agency']], 'choices' => [['value' => 'remote', 'label' => 'Remote'], ['value' => 'office', 'label' => 'Office']]]],
            ['name' => 'address', 'type' => FieldType::Text, 'is_required' => true, 'options' => ['unless' => ['kind' => ['remote']]]],
        ]);

        // `kind` is hidden for a direct inquiry, so its "remote" counts for nothing: the address is shown.
        $this->postJson($this->intake(), ['fields' => ['purpose' => 'direct', 'kind' => 'remote']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.address']);

        $this->postJson($this->intake(), ['fields' => ['purpose' => 'agency', 'kind' => 'remote']])->assertOk();
    }

    #[Test]
    public function a_ticked_consent_and_a_tick_among_several_hold_a_condition(): void
    {
        $this->form('contact', [
            ['name' => 'terms', 'type' => FieldType::Consent],
            ['name' => 'topics', 'type' => FieldType::Checkbox, 'options' => ['choices' => [['value' => 'news', 'label' => 'News'], ['value' => 'events', 'label' => 'Events']]]],
            ['name' => 'frequency', 'type' => FieldType::Text, 'is_required' => true, 'options' => ['when' => ['terms' => ['1'], 'topics' => ['news']]]],
        ]);

        $this->postJson($this->intake(), ['fields' => ['terms' => '1', 'topics' => ['events']]])->assertOk();
        $this->postJson($this->intake(), ['fields' => ['terms' => '1', 'topics' => ['events', 'news']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.frequency']);
        $this->postJson($this->intake(), ['fields' => ['topics' => ['news']]])->assertOk();
    }

    #[Test]
    public function the_page_prints_the_condition_and_leaves_the_browser_s_required_to_the_script(): void
    {
        $this->meetingForm();

        $html = Blade::render('<x-webx-inbox::form slug="contact" />');

        $this->assertStringContainsString('data-webx-when="'.e(json_encode(['fields.purpose' => ['agency', 'pre-register']])).'"', $html);
        $this->assertStringContainsString('If chosen: Agency meeting, Client pre-registration', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="fields\[agency\]"[^>]*data-webx-required="true"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="fields\[agency\]"[^>]*\srequired[\s>]/', $html);
        // The required mark stays: the field is required once it applies.
        $this->assertMatchesRegularExpression('/data-webx-field="fields.agency"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="fields\[name\]"[^>]*\srequired/', $html);
    }

    #[Test]
    public function a_form_without_conditions_prints_none_of_it(): void
    {
        $this->form();

        $html = Blade::render('<x-webx-inbox::form slug="contact" />');

        $this->assertStringNotContainsString('data-webx-when', $html);
        $this->assertStringNotContainsString('data-webx-unless', $html);
        $this->assertStringNotContainsString('webx-field__condition', $html);
        $this->assertStringNotContainsString('data-webx-required', $html);
    }

    #[Test]
    public function the_panel_refuses_a_condition_on_what_it_may_not_name(): void
    {
        $form = $this->meetingForm();
        $editor = $this->editor();

        $cases = [
            'a field the form has not' => ['ghost' => ['x']],
            'a field below' => ['note' => ['x']],
            'a field that is not a choice' => ['name' => ['Alex']],
            'a value the field does not offer' => ['purpose' => ['partner']],
        ];

        $this->field($form, ['name' => 'note', 'position' => 99]);

        foreach ($cases as $when) {
            $this->actingAs($editor, 'cms')
                ->putJson($this->api('fields/'.$this->id($form, 'agency')), [
                    'name' => 'agency',
                    'type' => 'text',
                    'title' => ['en' => 'Agency'],
                    'options' => ['when' => $when],
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['options.when']);
        }

        $this->assertSame(self::MEETING, Field::query()->where('name', 'agency')->sole()->options['when']);
    }

    #[Test]
    public function the_panel_keeps_what_a_condition_counts_on(): void
    {
        $form = $this->meetingForm();
        $editor = $this->editor();
        $purpose = $this->id($form, 'purpose');

        // A choice others count on taken away.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api("fields/{$purpose}"), [
                'name' => 'purpose',
                'type' => 'radio',
                'title' => ['en' => 'Purpose'],
                'options' => ['choices' => [['value' => 'direct', 'label' => 'Direct'], ['value' => 'agency', 'label' => 'Agency']]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['options.choices']);

        // A type that stops being a choice.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api("fields/{$purpose}"), ['name' => 'purpose', 'type' => 'text', 'title' => ['en' => 'Purpose']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        // Deleted.
        $this->actingAs($editor, 'cms')
            ->deleteJson($this->api("fields/{$purpose}"))
            ->assertStatus(422)
            ->assertJsonPath('errors.field.0', fn (string $message): bool => str_contains($message, 'agency'));

        // Dragged below what counts on it.
        $ids = $form->fields()->pluck('id')->all();
        $this->actingAs($editor, 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields/sorting"), ['ids' => [$ids[0], $ids[2], $ids[1]]])
            ->assertStatus(422);

        $this->assertSame($ids, $form->fields()->pluck('id')->all());
        $this->assertNotNull(Field::query()->find($purpose));
    }

    #[Test]
    public function a_rename_carries_the_conditions_along(): void
    {
        $form = $this->meetingForm();

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api('fields/'.$this->id($form, 'purpose')), [
                'name' => 'goal',
                'type' => 'radio',
                'title' => ['en' => 'Purpose'],
                'options' => ['choices' => self::choices()],
            ])
            ->assertOk();

        $this->assertSame(['goal' => ['agency', 'pre-register']], Field::query()->where('name', 'agency')->sole()->options['when']);
    }

    #[Test]
    public function a_new_field_may_name_any_choice_above_it(): void
    {
        $form = $this->meetingForm();

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields"), [
                'name' => 'office',
                'type' => 'text',
                'title' => ['en' => 'Office'],
                'options' => ['unless' => ['purpose' => ['direct', ' ', 'direct']], 'when' => []],
            ])
            ->assertCreated()
            ->assertJsonPath('data.options', ['unless' => ['purpose' => ['direct']]]);
    }

    #[Test]
    public function inbox_form_save_takes_and_checks_conditions(): void
    {
        $content = $this->content($this->agent('form_save', [
            'slug' => 'contact',
            'title' => 'Contact us',
            'fields' => [
                ['type' => 'radio', 'name' => 'purpose', 'title' => 'How can we help?', 'is_required' => true, 'options' => ['choices' => self::choices()]],
                ['type' => 'text', 'name' => 'agency', 'title' => 'Agency', 'options' => ['when' => self::MEETING]],
            ],
        ])->assertOk());

        $this->assertSame(self::MEETING, $content['fields'][1]['options']['when']);

        $this->agent('form_save', ['form' => 'contact', 'fields' => [['name' => 'agency', 'options' => ['when' => ['purpose' => ['partner']]]]]])
            ->assertHasErrors(['“partner”']);
        $this->agent('form_save', ['form' => 'contact', 'dry_run' => true, 'fields' => [['name' => 'note', 'type' => 'text', 'title' => 'Note'], ['name' => 'agency', 'options' => ['when' => ['note' => ['x']]]]]])
            ->assertHasErrors(['does not stand above']);
        $this->agent('form_save', ['form' => 'contact', 'fields' => [['name' => 'purpose', 'remove' => true]]])
            ->assertHasErrors(['“purpose”']);

        // A rename in the call takes the condition along, whether the call names it or not.
        $this->agent('form_save', ['form' => 'contact', 'fields' => [['id' => Field::query()->where('name', 'purpose')->value('id'), 'name' => 'goal']]])
            ->assertOk();
        $this->assertSame(['goal' => ['agency', 'pre-register']], Field::query()->where('name', 'agency')->sole()->options['when']);
    }

    #[Test]
    public function visibility_is_one_pass_over_the_fields_in_order(): void
    {
        $form = $this->meetingForm();

        $this->assertSame(
            ['name' => true, 'purpose' => true, 'agency' => false],
            Conditions::visible($form->liveFields, ['purpose' => 'direct']),
        );
        $this->assertSame(
            ['name' => true, 'purpose' => true, 'agency' => true],
            Conditions::visible($form->liveFields, ['purpose' => 'pre-register']),
        );
        $this->assertSame(['when' => ['a' => ['1', 'x']]], Conditions::of(['when' => ['a' => [true, 'x', ''], '' => ['y']], 'unless' => []]));
    }

    private function meetingForm(): Form
    {
        return $this->form('contact', [
            ['name' => 'name', 'type' => FieldType::Text, 'is_required' => true],
            ['name' => 'purpose', 'type' => FieldType::Radio, 'is_required' => true, 'options' => ['choices' => self::choices()]],
            ['name' => 'agency', 'type' => FieldType::Text, 'is_required' => true, 'options' => ['when' => self::MEETING]],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function choices(): array
    {
        return [
            ['value' => 'direct', 'label' => ['en' => 'Direct inquiry']],
            ['value' => 'agency', 'label' => ['en' => 'Agency meeting']],
            ['value' => 'pre-register', 'label' => ['en' => 'Client pre-registration']],
        ];
    }

    private function id(Form $form, string $name): int
    {
        return (int) $form->fields()->where('name', $name)->value('id');
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
