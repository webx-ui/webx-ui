<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Types\RepeaterType;

final class RepeaterTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-localization.locales', [
            ['code' => 'ru', 'default' => true],
            ['code' => 'uk'],
        ]);

        Screens::register('settings.index', [
            [
                'id' => 'card',
                'type' => 'wx-card',
                'children' => [
                    ['id' => 'phone', 'type' => 'wx-input', 'name' => 'contacts.phone'],
                    [
                        'id' => 'offices',
                        'type' => 'wx-repeater',
                        'name' => 'contacts.offices',
                        'label' => 'Offices',
                        'children' => [
                            ['id' => 'city', 'type' => 'wx-input', 'name' => 'city', 'label' => 'City', 'localized' => true],
                            ['id' => 'floor', 'type' => 'wx-input-number', 'name' => 'floor', 'label' => 'Floor', 'props' => ['min' => 1, 'max' => 30]],
                            ['id' => 'main', 'type' => 'wx-switch', 'name' => 'main'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function values(): ScreenValues
    {
        return $this->app->make(ScreenValues::class);
    }

    #[Test]
    public function the_children_of_a_repeater_are_not_fields_of_the_screen(): void
    {
        $names = array_map(
            static fn (array $node): string => (string) $node['name'],
            $this->app->make(ScreenRegistry::class)->fields('settings.index'),
        );

        $this->assertSame(['contacts.phone', 'contacts.offices'], $names);
    }

    #[Test]
    public function keeps_the_keys_the_children_name_and_casts_each_by_its_type(): void
    {
        $stored = $this->values()->validate('settings.index', [
            'contacts.offices' => [
                ['city' => ['ru' => 'Киев', 'uk' => 'Київ', 'xx' => 'dropped'], 'floor' => '3', 'main' => '1', 'stray' => 'ignored'],
                ['city' => ['ru' => 'Львов'], 'floor' => 2, 'main' => false],
            ],
        ]);

        $this->assertSame([
            'contacts.offices' => [
                ['city' => ['ru' => 'Киев', 'uk' => 'Київ'], 'floor' => 3, 'main' => true],
                ['city' => ['ru' => 'Львов'], 'floor' => 2, 'main' => false],
            ],
        ], $stored);
    }

    #[Test]
    public function a_bad_value_is_named_by_its_row_and_field(): void
    {
        try {
            $this->values()->validate('settings.index', [
                'contacts.offices' => [
                    ['floor' => 3, 'city' => ['uk' => ['not', 'a', 'string']]],
                    ['floor' => 99],
                ],
            ]);

            $this->fail('the repeater accepted a floor above its maximum');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            // Under the row and the field, where the panel marks the row and opens it at the field.
            $this->assertSame(['contacts.offices.0.city.uk', 'contacts.offices.1.floor'], array_keys($errors));
            $this->assertStringContainsString('Floor', $errors['contacts.offices.1.floor'][0]);
            $this->assertStringNotContainsString('Row', $errors['contacts.offices.1.floor'][0]);
        }
    }

    #[Test]
    public function a_caller_that_only_runs_the_rules_still_hears_which_row(): void
    {
        $node = $this->app->make(ScreenRegistry::class)->fields('settings.index')[1];
        $rules = $this->app->make(FieldTypes::class)->get('wx-repeater')?->rules($node) ?? [];

        $validator = Validator::make(['value' => [['floor' => 3], ['floor' => 99]]], ['value' => $rules]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('Row 2', $validator->errors()->first('value'));
    }

    #[Test]
    public function a_row_that_is_not_a_set_of_fields_is_reported_rather_than_stored(): void
    {
        $this->expectException(ValidationException::class);

        $this->values()->validate('settings.index', ['contacts.offices' => ['just a string']]);
    }

    #[Test]
    public function an_empty_list_is_a_value_like_any_other(): void
    {
        $this->assertSame(
            ['contacts.offices' => []],
            $this->values()->validate('settings.index', ['contacts.offices' => []]),
        );
    }

    #[Test]
    public function the_site_reads_one_language_per_row(): void
    {
        $resolved = $this->values()->resolveAll('settings.index', [
            'contacts.offices' => [
                ['city' => ['ru' => 'Киев', 'uk' => 'Київ'], 'floor' => 3, 'main' => true],
                ['city' => ['uk' => 'Львів'], 'floor' => 2],
            ],
        ], 'uk');

        $this->assertSame([
            ['city' => 'Київ', 'floor' => 3, 'main' => true],
            ['city' => 'Львів', 'floor' => 2, 'main' => null],
        ], $resolved['contacts.offices']);
    }

    #[Test]
    public function a_value_that_is_not_a_list_reads_as_an_empty_one(): void
    {
        $resolved = $this->values()->resolveAll('settings.index', ['contacts.offices' => null]);

        $this->assertSame([], $resolved['contacts.offices']);
    }

    #[Test]
    public function a_key_no_field_names_is_refused_when_new_and_let_through_when_held(): void
    {
        try {
            $this->values()->validate('settings.index', [
                'contacts.offices' => [['city' => ['ru' => 'Киев'], 'street' => 'Main st.']],
            ], held: []);

            $this->fail('the repeater kept quiet about a key none of its fields names');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['contacts.offices.0.street' => ['This row has no field "street". A row takes: city, floor, main.']],
                $exception->errors(),
            );
        }

        // A field a screen has since removed travels back with the panel's rows: dropped, not refused.
        $stored = $this->values()->validate('settings.index', [
            'contacts.offices' => [['city' => ['ru' => 'Киев'], 'street' => 'Main st.']],
        ], held: ['contacts.offices' => [['city' => ['ru' => 'Киев'], 'street' => 'Old st.']]]);

        $this->assertSame(['contacts.offices' => [['city' => ['ru' => 'Киев']]]], $stored);
    }

    #[Test]
    public function the_shape_of_one_item_names_each_key_and_what_it_takes(): void
    {
        $node = $this->app->make(ScreenRegistry::class)->fields('settings.index')[1];
        $type = $this->app->make(FieldTypes::class)->get('wx-repeater');

        $this->assertInstanceOf(RepeaterType::class, $type);
        $this->assertSame([
            'city' => ['type' => 'wx-input', 'localized' => true, 'value' => 'string'],
            'floor' => ['type' => 'wx-input-number', 'value' => 'number', 'min' => 1, 'max' => 30],
            'main' => ['type' => 'wx-switch', 'value' => 'boolean'],
        ], $type->itemShape($node));
    }
}
