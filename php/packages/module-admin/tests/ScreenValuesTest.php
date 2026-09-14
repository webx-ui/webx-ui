<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\ScreenValues;

final class ScreenValuesTest extends TestCase
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
                    ['id' => 'name', 'type' => 'wx-input', 'name' => 'general.project-name', 'label' => 'Project name', 'localized' => true],
                    ['id' => 'rows', 'type' => 'wx-input-number', 'name' => 'general.rows', 'props' => ['min' => 1, 'max' => 10]],
                    ['id' => 'indexing', 'type' => 'wx-switch', 'name' => 'seo.indexing'],
                    ['id' => 'kind', 'type' => 'wx-select', 'name' => 'general.kind', 'props' => ['options' => [['label' => 'Shop', 'value' => 'shop'], 'blog']]],
                    ['id' => 'color', 'type' => 'wx-color-picker', 'name' => 'general.color'],
                    ['id' => 'secret', 'type' => 'wx-input', 'name' => 'general.secret', 'can' => 'settings.manage'],
                    ['id' => 'map', 'type' => 'map', 'name' => 'contacts.map'],
                ],
            ],
        ]);
    }

    private function values(): ScreenValues
    {
        return $this->app->make(ScreenValues::class);
    }

    #[Test]
    public function keeps_what_the_tree_names_and_casts_it_by_type(): void
    {
        $stored = $this->values()->validate('settings.index', [
            'general.project-name' => ['ru' => 'Акме', 'uk' => 'Акме', 'xx' => 'dropped'],
            'general.rows' => '5',
            'seo.indexing' => '1',
            'general.kind' => 'blog',
            'general.color' => '#FF8800',
            'contacts.map' => ['lat' => 1, 'lng' => 2],
            'not.in.tree' => 'ignored',
        ], static fn (string $permission): bool => true);

        $this->assertSame([
            'general.project-name' => ['ru' => 'Акме', 'uk' => 'Акме'],
            'general.rows' => 5,
            'seo.indexing' => true,
            'general.kind' => 'blog',
            'general.color' => '#ff8800',
            'contacts.map' => ['lat' => 1, 'lng' => 2],
        ], $stored);
    }

    #[Test]
    public function a_node_the_administrator_may_not_see_is_closed_for_writing(): void
    {
        $stored = $this->values()->validate(
            'settings.index',
            ['general.secret' => 'x', 'general.kind' => 'shop'],
            static fn (string $permission): bool => false,
        );

        $this->assertSame(['general.kind' => 'shop'], $stored);
    }

    #[Test]
    public function reports_every_bad_value_under_its_own_key(): void
    {
        try {
            $this->values()->validate('settings.index', [
                'general.rows' => 42,
                'general.kind' => 'bank',
                'general.color' => 'red',
                'general.project-name' => ['ru' => str_repeat('x', 2001)],
            ]);
            $this->fail('Validation should have failed.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            // In document order, the way the fields sit on the screen.
            $this->assertSame(['general.project-name', 'general.rows', 'general.kind', 'general.color'], array_keys($errors));
            $this->assertStringContainsString('Project name', $errors['general.project-name'][0]);
        }
    }

    #[Test]
    public function resolves_a_localized_value_for_the_site_with_fallbacks(): void
    {
        $values = $this->values();
        $stored = ['general.project-name' => ['ru' => 'Акме', 'uk' => ''], 'general.rows' => 5];

        $resolved = $values->resolveAll('settings.index', $stored, 'uk');

        $this->assertSame('Акме', $resolved['general.project-name']);
        $this->assertSame(5, $resolved['general.rows']);
        $this->assertNull($resolved['contacts.map']);
    }
}
