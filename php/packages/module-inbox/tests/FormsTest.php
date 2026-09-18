<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Inbox\Exceptions\FormHasSubmissions;
use WebxUi\Inbox\Exceptions\StatusInUse;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;

/**
 * The rules that hold whatever else is done to a form (§2.3, §2.4, §3, §13).
 */
final class FormsTest extends TestCase
{
    #[Test]
    public function the_migration_seeds_the_five_statuses_a_panel_starts_with(): void
    {
        $this->assertSame(
            ['new', 'in-progress', 'done', 'rejected', 'spam'],
            Status::query()->orderBy('position')->pluck('key')->all(),
        );

        $this->assertSame('new', Status::default()?->key);
        $this->assertSame('spam', Status::spam()?->key);

        // Seeded in every language the package ships words in, so a panel in Polish does not
        // start with five English badges.
        $this->assertSame('Новая', Status::query()->where('key', 'new')->sole()->getTranslation('title', 'ru'));
        $this->assertSame('Zakończone', Status::query()->where('key', 'done')->sole()->getTranslation('title', 'pl'));
    }

    #[Test]
    public function only_one_status_is_the_default(): void
    {
        Status::query()->where('key', 'done')->sole()->update(['is_default' => true]);

        $this->assertSame(['done'], Status::query()->where('is_default', true)->pluck('key')->all());
    }

    #[Test]
    public function a_status_with_submissions_is_not_deleted(): void
    {
        $this->form();
        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'a@example.test']])->assertOk();

        $this->expectException(StatusInUse::class);

        Status::query()->where('key', 'new')->sole()->delete();
    }

    #[Test]
    public function a_form_with_submissions_is_switched_off_rather_than_deleted(): void
    {
        $form = $this->form();
        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'a@example.test']])->assertOk();

        $this->expectException(FormHasSubmissions::class);

        $form->delete();
    }

    #[Test]
    public function the_database_refuses_it_too(): void
    {
        $form = $this->form();
        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'a@example.test']])->assertOk();

        // A mass delete raises no model events, so the rule above is not consulted at all —
        // which is why the foreign key is restricted rather than cascading (§2.4).
        $this->expectException(QueryException::class);

        Form::query()->whereKey($form->getKey())->delete();
    }

    #[Test]
    public function an_empty_form_is_deleted_with_its_fields(): void
    {
        $form = $this->form();

        $form->delete();

        $this->assertSame(0, Form::query()->count());
        $this->assertSame(0, Field::withTrashed()->count());
    }

    #[Test]
    public function a_deleted_field_leaves_the_form_and_stays_in_the_submissions_it_is_in(): void
    {
        $form = $this->form();
        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'a@example.test', 'message' => 'Hello'],
        ])->assertOk();

        $form->fields()->where('name', 'message')->sole()->delete();

        // Gone from the form...
        $this->assertSame(['name', 'email'], $form->refresh()->fields->pluck('name')->all());

        // ...and still in what arrived through it, which is the whole of §2.3.
        $this->assertSame('Hello', Submission::query()->sole()->value('message')?->value);

        // A new submission simply does not have it.
        $this->postJson($this->intake(), ['fields' => ['name' => 'Bob', 'email' => 'b@example.test']])->assertOk();
        $this->assertNull(Submission::query()->latest('id')->first()->value('message'));
    }

    #[Test]
    public function a_field_nobody_named_answers_to_its_id(): void
    {
        $form = $this->form('quiz', [['type' => FieldType::Text, 'is_required' => true]]);
        $field = $form->fields->sole();

        $this->assertSame('f'.$field->getKey(), $field->key());

        $this->postJson($this->intake('quiz'), ['fields' => ['f'.$field->getKey() => 'Ada']])->assertOk();

        $this->assertSame('Ada', Submission::query()->sole()->value('f'.$field->getKey())?->value);
    }

    #[Test]
    public function a_setting_with_a_dot_in_it_is_one_key_and_not_a_path(): void
    {
        // The same trap as the field names of `module-settings`: `thank-you.heading` is a key
        // that happens to contain a dot, and anything reading it as a path finds nothing.
        $form = $this->form('contact', [], ['thank-you.heading' => ['en' => 'Thanks']]);

        $this->assertSame(['en' => 'Thanks'], $form->option('thank-you.heading'));
        $this->assertNull($form->option('thank-you'));
    }

    #[Test]
    public function the_section_declares_itself_with_its_three_permissions(): void
    {
        $modules = $this->app->make(ModuleRegistry::class)->all();
        $module = collect($modules)->first(fn (Module $module): bool => $module->id() === 'inbox');

        $this->assertInstanceOf(Module::class, $module);
        $this->assertSame(['inbox.view', 'inbox.update', 'inbox.manage'], $module->permissions());

        // Top level and first in it: this is the section somebody opens in the morning (§2.18).
        $this->assertNull($module->group());
        $this->assertSame(100, $module->order());
    }
}
