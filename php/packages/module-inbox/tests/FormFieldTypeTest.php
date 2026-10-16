<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Inbox\Relations\FormTarget;
use WebxUi\Inbox\Screens\FormFieldType;

/**
 * `wx-inbox-form`: a form chosen by its slug, which is what the blocks that print a form have
 * always kept — so the stored value does not change, and a slug no form has stops being silent.
 */
final class FormFieldTypeTest extends TestCase
{
    #[Test]
    public function the_type_is_registered_and_keeps_the_slug(): void
    {
        $type = $this->app->make(FieldTypes::class)->get('wx-inbox-form');

        $this->assertInstanceOf(FormFieldType::class, $type);
        $this->assertSame('contact', $type->store('  contact ', []));
        $this->assertNull($type->store('', []), 'empty is no form');
        $this->assertNull($type->store(null, []));
        $this->assertSame('contact', $type->resolve('contact', []));
        $this->assertNull($type->resolve(['en' => 'contact'], []));
    }

    #[Test]
    public function a_slug_no_form_has_is_a_problem_that_names_the_forms_there_are(): void
    {
        $type = new FormFieldType;

        $this->assertSame(
            ['The Inbox has no form “contact” — it has no forms yet.'],
            $type->newValueProblems('contact', []),
        );

        $this->form('contact');
        $this->form('callback')->update(['is_enabled' => false]);

        $this->assertSame([], $type->newValueProblems('contact', []));
        $this->assertSame([], $type->newValueProblems('callback', []), 'a form switched off is still a form');
        $this->assertSame([], $type->newValueProblems('', []));
        $this->assertSame(
            ['The Inbox has no form “contakt”. It has: contact, callback.'],
            $type->newValueProblems('contakt', []),
        );
    }

    #[Test]
    public function a_screen_refuses_a_new_unknown_slug_and_keeps_one_it_already_held(): void
    {
        $this->form('contact');
        $this->app->make(ScreenRegistry::class)->register('test.inbox-form', [
            ['id' => 'form', 'type' => 'wx-inbox-form', 'name' => 'form'],
        ]);
        $values = $this->app->make(ScreenValues::class);

        $this->assertSame(['form' => 'contact'], $values->validate('test.inbox-form', ['form' => 'contact'], held: []));
        $this->assertSame(['form' => null], $values->validate('test.inbox-form', ['form' => ''], held: []));

        // A form deleted after it was chosen must not stop the record from being saved again.
        $this->assertSame(['form' => 'gone'], $values->validate('test.inbox-form', ['form' => 'gone'], held: ['form' => 'gone']));

        try {
            $values->validate('test.inbox-form', ['form' => 'gone'], held: ['form' => 'contact']);
            $this->fail('A slug no form has was accepted.');
        } catch (ValidationException $refused) {
            $this->assertSame(['form' => ['The Inbox has no form “gone”. It has: contact.']], $refused->errors());
        }
    }

    #[Test]
    public function the_picker_finds_a_form_by_its_slug_and_puts_the_exact_one_first(): void
    {
        $this->form('callback-contact');
        $this->form('contact');
        $this->form('feedback');

        $rows = $this->actingAs($this->editor([]), 'cms')
            ->getJson('/api/cms/relations/'.FormTarget::KEY.'?q=contact')
            ->assertOk()
            ->json('data');

        $this->assertIsArray($rows);
        $this->assertSame(['contact', 'callback-contact'], array_column($rows, 'slug'));
        $this->assertSame('Contact', $rows[0]['title']);

        // By the title as well, the way every target is searched.
        $byTitle = $this->actingAs($this->editor([]), 'cms')
            ->getJson('/api/cms/relations/'.FormTarget::KEY.'?q=Feedb')
            ->json('data');

        $this->assertSame(['feedback'], array_column($byTitle, 'slug'));
    }
}
