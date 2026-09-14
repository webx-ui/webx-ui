<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValues;

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
    public function a_bad_value_says_which_row_it_was_in(): void
    {
        try {
            $this->values()->validate('settings.index', [
                'contacts.offices' => [
                    ['floor' => 3],
                    ['floor' => 99],
                ],
            ]);

            $this->fail('the repeater accepted a floor above its maximum');
        } catch (ValidationException $exception) {
            $messages = $exception->errors()['contacts.offices'] ?? [];

            $this->assertCount(1, $messages);
            $this->assertStringContainsString('Row 2', $messages[0]);
            $this->assertStringContainsString('Floor', $messages[0]);
        }
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
}
