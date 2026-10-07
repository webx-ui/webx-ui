<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\ScreenException;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\ScreenValidator;
use WebxUi\Admin\Screens\ScreenValues;

/**
 * A field's `default`: what the panel draws and the site reads while nothing is stored.
 */
final class FieldDefaultsTest extends TestCase
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
                    ['id' => 'popup', 'type' => 'wx-switch', 'name' => 'popup.enabled', 'default' => true],
                    ['id' => 'rows', 'type' => 'wx-slider', 'name' => 'general.rows', 'default' => 20, 'props' => ['min' => 0, 'max' => 100]],
                    ['id' => 'title', 'type' => 'wx-input', 'name' => 'popup.title', 'localized' => true, 'default' => 'Hello'],
                    ['id' => 'plain', 'type' => 'wx-input', 'name' => 'popup.plain'],
                    [
                        'id' => 'offices',
                        'type' => 'wx-repeater',
                        'name' => 'contacts.offices',
                        'children' => [
                            ['id' => 'city', 'type' => 'wx-input', 'name' => 'city'],
                            ['id' => 'open', 'type' => 'wx-switch', 'name' => 'open', 'default' => true],
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
    public function the_closed_key_set_takes_a_default_on_a_node_and_in_a_set_operation(): void
    {
        $this->assertSame([], ScreenValidator::screen([['id' => 'a', 'type' => 'wx-switch', 'name' => 'a', 'default' => true]]));
        $this->assertSame([], ScreenValidator::patch([['op' => 'set', 'target' => 'a', 'default' => false]]));
    }

    #[Test]
    public function nothing_stored_reads_as_the_default(): void
    {
        $resolved = $this->values()->resolveAll('settings.index', []);

        $this->assertTrue($resolved['popup.enabled']);
        $this->assertSame(20, $resolved['general.rows']);
        $this->assertSame('Hello', $resolved['popup.title']);
        $this->assertNull($resolved['popup.plain']);
    }

    #[Test]
    public function a_stored_value_wins_over_the_default_even_when_it_is_false(): void
    {
        $resolved = $this->values()->resolveAll('settings.index', [
            'popup.enabled' => false,
            'general.rows' => 0,
            'popup.title' => ['uk' => 'Привіт'],
        ], 'uk');

        $this->assertFalse($resolved['popup.enabled']);
        $this->assertSame(0, $resolved['general.rows']);
        $this->assertSame('Привіт', $resolved['popup.title']);
    }

    #[Test]
    public function a_localized_field_with_every_language_empty_reads_its_one_default(): void
    {
        $node = $this->app->make(ScreenRegistry::class)->fields('settings.index')[2];

        $this->assertSame('Hello', $this->values()->resolve($node, ['ru' => '', 'uk' => null], 'uk'));
    }

    #[Test]
    public function a_field_of_a_repeater_row_reads_its_default_too(): void
    {
        $resolved = $this->values()->resolveAll('settings.index', [
            'contacts.offices' => [['city' => 'Kyiv'], ['city' => 'Lviv', 'open' => false]],
        ]);

        $this->assertSame([
            ['city' => 'Kyiv', 'open' => true],
            ['city' => 'Lviv', 'open' => false],
        ], $resolved['contacts.offices']);
    }

    #[Test]
    public function a_save_that_does_not_send_the_field_stores_nothing_for_it(): void
    {
        $stored = $this->values()->validate('settings.index', ['popup.plain' => 'x']);

        $this->assertSame(['popup.plain' => 'x'], $stored);
    }

    #[Test]
    public function a_default_the_type_would_refuse_breaks_the_screen(): void
    {
        Screens::register('demo.bad', [
            ['id' => 'flag', 'type' => 'wx-switch', 'name' => 'flag', 'default' => 'often'],
        ]);

        $this->expectException(ScreenException::class);
        $this->expectExceptionMessageMatches('/"flag": the default is not a value of its type/');

        $this->app->make(ScreenRegistry::class)->tree('demo.bad');
    }

    #[Test]
    public function a_default_laid_on_by_a_patch_is_checked_as_well(): void
    {
        Screens::register('demo.patched', [
            ['id' => 'email', 'type' => 'wx-input', 'name' => 'email', 'props' => ['type' => 'email']],
        ]);
        Screens::extend('demo.patched', [['op' => 'set', 'target' => 'email', 'default' => 'not-an-email']]);

        $this->expectException(ScreenException::class);

        $this->app->make(ScreenRegistry::class)->tree('demo.patched');
    }

    #[Test]
    public function a_default_on_a_node_without_a_name_breaks_the_screen(): void
    {
        Screens::register('demo.layout', [
            ['id' => 'card', 'type' => 'wx-card', 'default' => true],
        ]);

        $this->expectException(ScreenException::class);
        $this->expectExceptionMessageMatches('/"card": "default" belongs to a field/');

        $this->app->make(ScreenRegistry::class)->tree('demo.layout');
    }
}
