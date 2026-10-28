<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Users\Doctor\FieldsCheck;
use WebxUi\Users\Fields\Placement;
use WebxUi\Users\Fields\UserField;
use WebxUi\Users\Fields\UserFields;

/**
 * A field that cannot work is refused loudly, not dropped (spec §10.1, §10.2): a taken name, a type
 * the panel does not know, a choice without options, a type the site's forms have no field for in
 * a place the visitor fills in. Without the settings module every field is what it declares.
 */
final class FieldDeclarationsTest extends TestCase
{
    /** @return iterable<string, array{UserField, string}> */
    public static function refused(): iterable
    {
        yield 'a column of users' => [new UserField('email', 'wx-input', 'E-mail'), 'a column of `users`'];
        yield 'not a name' => [new UserField('Club_Card', 'wx-input', 'Club card'), 'lower-case letters'];
        yield 'an unknown type' => [new UserField('mood', 'wx-mood', 'Mood', placements: [Placement::Panel]), 'not registered in FieldTypes'];
        yield 'a choice without options' => [new UserField('size', 'wx-select', 'Size'), 'nothing to choose from'];
        yield 'a radio group without options' => [new UserField('size', 'wx-radio-group', 'Size', props: ['options' => null]), 'nothing to choose from'];
        yield 'a panel type in the registration' => [new UserField('colour', 'wx-color-picker', 'Colour'), 'can be only in the panel'];
        yield 'an input of a type the site has not' => [new UserField('secret', 'wx-input', 'Secret', props: ['type' => 'password']), 'can be only in the panel'];
        yield 'no place' => [new UserField('nowhere', 'wx-input', 'Nowhere', placements: []), 'in no place'];
    }

    #[Test]
    #[DataProvider('refused')]
    public function a_field_that_cannot_work_is_an_exception_at_its_registration(UserField $field, string $why): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($why);

        $this->app->make(UserFields::class)->register($field);
    }

    #[Test]
    public function a_name_is_declared_once(): void
    {
        $fields = $this->app->make(UserFields::class);
        $fields->register(new UserField('company', 'wx-input', 'Company', source: 'b2b'));

        $this->expectExceptionMessage('declared twice: b2b has it already');

        $fields->register(new UserField('company', 'wx-textarea', 'Company'));
    }

    #[Test]
    public function a_panel_type_is_fine_in_the_panel_and_on_the_site_once_it_has_a_pair(): void
    {
        $fields = $this->app->make(UserFields::class);
        $fields->register(new UserField('colour', 'wx-color-picker', 'Colour', placements: [Placement::Panel]));

        $fields->siteType('wx-color-picker', 'text');
        $fields->register(new UserField('shade', 'wx-color-picker', 'Shade'));

        $this->assertSame(['colour', 'shade'], array_map(static fn (UserField $field): string => $field->name, $fields->all()));
        $this->assertSame(['shade'], array_map(static fn (UserField $field): string => $field->name, $fields->for(Placement::Registration)));
        $this->assertSame('text', $fields->siteTypeOf($fields->get('shade') ?? $this->fail()));

        $this->expectExceptionMessage('is not a field of the site');
        $fields->siteType('wx-media', 'file');
    }

    #[Test]
    public function the_pairs_of_the_spec_become_the_fields_of_the_site(): void
    {
        $fields = $this->app->make(UserFields::class);
        $options = ['options' => ['a', 'b']];
        $pairs = [
            'text' => new UserField('a', 'wx-input', 'A'),
            'tel' => new UserField('b', 'wx-input', 'B', props: ['type' => 'tel']),
            'textarea' => new UserField('c', 'wx-textarea', 'C'),
            'number' => new UserField('d', 'wx-input-number', 'D'),
            'checkbox' => new UserField('e', 'wx-switch', 'E'),
            'select' => new UserField('f', 'wx-segmented', 'F', props: $options),
            'radio' => new UserField('g', 'wx-radio-group', 'G', props: $options),
            'date' => new UserField('h', 'wx-date-picker', 'H'),
        ];

        foreach ($pairs as $type => $field) {
            $fields->register($field);
            $this->assertSame($type, $fields->siteTypeOf($field), $field->type);
        }
    }

    #[Test]
    public function a_wrong_field_in_the_config_is_refused_on_the_first_question_and_named_by_the_doctor(): void
    {
        config()->set('webx-users.fields', ['colour' => ['type' => 'wx-color-picker', 'label' => 'Colour']]);

        $diagnoses = $this->app->make(FieldsCheck::class)->run();
        $this->assertSame(Diagnosis::FAIL, $diagnoses[0]->state);
        $this->assertStringContainsString('«colour»', $diagnoses[0]->detail);

        // The page meets the same refusal — and keeps meeting it, nothing half-taken.
        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(UserFields::class)->all();
    }

    #[Test]
    public function without_the_settings_a_field_is_what_it_declares(): void
    {
        $this->app->make(UserFields::class)->register(new UserField('company', 'wx-input', 'Company', required: true));

        $this->postJson('/account/register', ['email' => 'anna@example.test', 'password' => self::PASSWORD])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.company'])
            ->assertJsonMissingValidationErrors(['first_name', 'phone']);
    }
}
