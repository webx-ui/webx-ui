<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;

/**
 * The questions of a form, as the dialog writes them (§12).
 */
final class PanelFieldsTest extends TestCase
{
    #[Test]
    public function it_lists_the_questions_in_the_order_they_are_asked(): void
    {
        $form = $this->form('contact');

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api("forms/{$form->getKey()}/fields"))
            ->assertOk();

        $this->assertSame(['name', 'email', 'message'], array_column($response->json('data'), 'name'));
    }

    #[Test]
    public function a_field_without_a_name_answers_to_its_number(): void
    {
        $form = $this->form('contact', [['type' => FieldType::Text, 'title' => 'Anything']]);

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api("forms/{$form->getKey()}/fields"))
            ->assertOk();

        $field = $response->json('data.0');

        $this->assertNull($field['name']);
        $this->assertSame('f'.$field['id'], $field['key']);
    }

    #[Test]
    public function it_writes_a_question_at_the_end_of_the_form(): void
    {
        $form = $this->form('contact');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields"), [
                'name' => 'budget',
                'type' => 'select',
                'title' => ['en' => 'Budget'],
                'is_required' => true,
                'options' => [
                    'choices' => [
                        ['value' => 'small', 'label' => ['en' => 'Under a thousand']],
                        ['value' => '', 'label' => ['en' => 'Nameless, and therefore dropped']],
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'budget')
            ->assertJsonPath('data.options.choices.0.value', 'small')
            ->assertJsonCount(1, 'data.options.choices');

        $this->assertSame(3, Field::query()->where('name', 'budget')->sole()->position);
    }

    #[Test]
    public function it_keeps_only_the_settings_the_type_has(): void
    {
        $form = $this->form('contact');

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields"), [
                'name' => 'about',
                'type' => 'textarea',
                'title' => 'About',
                // `choices` belongs to a drop-down, and a leftover array here would be a
                // column of empty strings in the export a year from now.
                'options' => ['rows' => 8, 'maxlength' => 2000, 'choices' => [['value' => 'x']]],
            ])
            ->assertCreated();

        $this->assertSame(['maxlength' => 2000, 'rows' => 8], $response->json('data.options'));
    }

    #[Test]
    public function two_live_questions_cannot_share_a_name(): void
    {
        $form = $this->form('contact');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields"), [
                'name' => 'email',
                'type' => 'email',
                'title' => 'Another e-mail',
            ])
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function a_deleted_question_gives_its_name_back(): void
    {
        $form = $this->form('contact');
        $email = $form->fields()->where('name', 'email')->sole();

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api("fields/{$email->getKey()}"))
            ->assertNoContent();

        // Softly: the answers already given through it keep pointing at it (§2.3).
        $this->assertSoftDeleted($email);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields"), [
                'name' => 'email',
                'type' => 'email',
                'title' => 'E-mail',
            ])
            ->assertCreated();
    }

    #[Test]
    public function a_name_with_a_dot_in_it_is_refused(): void
    {
        $form = $this->form('contact');

        // `fields.company.name` would make the intake look for a nested array and report the
        // error under a key nothing on the page has.
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields"), [
                'name' => 'company.name',
                'type' => 'text',
                'title' => 'Company',
            ])
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function it_saves_the_order_of_the_questions_of_one_form_only(): void
    {
        $form = $this->form('contact');
        $other = $this->form('callback', [['name' => 'phone', 'type' => FieldType::Tel]]);

        [$name, $email, $message] = $form->fields->all();
        $phone = $other->fields->sole();

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields/sorting"), [
                'ids' => [$message->getKey(), $email->getKey(), $name->getKey(), $phone->getKey()],
            ])
            ->assertNoContent();

        $this->assertSame(0, $message->refresh()->position);
        $this->assertSame(2, $name->refresh()->position);
        // A list drawn from one form must not be able to renumber another one's.
        $this->assertSame(0, $phone->refresh()->position);
    }

    #[Test]
    public function writing_a_question_needs_the_permission_for_it(): void
    {
        $form = $this->form('contact');

        $this->actingAs($this->editor(['inbox.view', 'inbox.update']), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/fields"), [
                'name' => 'sneaky',
                'type' => 'text',
                'title' => 'No',
            ])
            ->assertForbidden();
    }
}
