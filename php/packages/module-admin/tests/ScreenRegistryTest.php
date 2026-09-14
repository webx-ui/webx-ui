<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use WebxUi\Admin\Screens\ScreenException;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;

final class ScreenRegistryTest extends PhpUnitTestCase
{
    private const FIXTURE = __DIR__.'/Fixtures/screens/settings.json';

    #[Test]
    public function registers_a_screen_from_a_file_and_lists_its_name(): void
    {
        $screens = new ScreenRegistry;
        $screens->register('settings.index', self::FIXTURE);

        $this->assertTrue($screens->has('settings.index'));
        $this->assertSame(['settings.index'], $screens->names());
        $this->assertSame('tabs', $screens->tree('settings.index')[0]['id']);
        $this->assertSame(
            ['general.project-name', 'general.secret', 'contacts.map'],
            array_column($screens->fields('settings.index'), 'name'),
        );
    }

    #[Test]
    public function a_patch_registered_before_the_screen_still_applies(): void
    {
        $screens = new ScreenRegistry;
        $screens->extend('settings.index', [['op' => 'remove', 'target' => 'map']]);
        $screens->register('settings.index', self::FIXTURE);
        $screens->extend('settings.index', [
            ['op' => 'add', 'target' => 'main', 'node' => ['id' => 'phone', 'type' => 'wx-input', 'name' => 'contacts.phone']],
        ]);

        $fields = array_column($screens->fields('settings.index'), 'name');

        $this->assertSame(['general.project-name', 'general.secret', 'contacts.phone'], $fields);
    }

    #[Test]
    public function rendering_cuts_out_what_the_administrator_may_not_see_and_translates_the_rest(): void
    {
        $screens = new ScreenRegistry;
        $screens->register('settings.index', self::FIXTURE);

        $root = $screens->render(
            'settings.index',
            static fn (string $permission): bool => false,
            static fn (string $key): string => strtoupper($key),
        );

        $this->assertNull(Tree::find($root, 'secret'));
        $this->assertSame('WEBX-ADMIN::NAV.SYSTEM', Tree::find($root, 'general')['label'] ?? null);
        $this->assertSame('Map', Tree::find($root, 'map')['label'] ?? null);

        $allowed = $screens->render('settings.index', static fn (string $permission): bool => true);

        $this->assertNotNull(Tree::find($allowed, 'secret'));
        $this->assertSame('trans::webx-admin::nav.system', Tree::find($allowed, 'general')['label'] ?? null);
    }

    #[Test]
    public function a_bad_name_a_duplicate_and_an_unknown_screen_throw(): void
    {
        $screens = new ScreenRegistry;

        try {
            $screens->register('Settings', []);
            $this->fail('The name should have been refused.');
        } catch (ScreenException $exception) {
            $this->assertStringContainsString('[Settings]', $exception->getMessage());
        }

        $screens->register('settings.index', []);

        try {
            $screens->register('settings.index', []);
            $this->fail('The duplicate should have been refused.');
        } catch (ScreenException $exception) {
            $this->assertStringContainsString('already registered', $exception->getMessage());
        }

        $this->expectException(ScreenException::class);
        $screens->tree('settings.nope');
    }

    #[Test]
    public function an_invalid_tree_is_refused_at_registration(): void
    {
        $this->expectException(ScreenException::class);
        $this->expectExceptionMessage('unknown key "childrens"');

        (new ScreenRegistry)->register('settings.index', [
            ['id' => 'a', 'type' => 'wx-card', 'childrens' => []],
        ]);
    }

    #[Test]
    public function an_invalid_patch_is_refused_at_registration_and_a_lost_target_when_built(): void
    {
        $screens = new ScreenRegistry;
        $screens->register('settings.index', self::FIXTURE);

        try {
            $screens->extend('settings.index', [['op' => 'swap', 'target' => 'tabs']]);
            $this->fail('The operation should have been refused.');
        } catch (ScreenException $exception) {
            $this->assertStringContainsString('"op" must be one of', $exception->getMessage());
        }

        $screens->extend('settings.index', [['op' => 'remove', 'target' => 'renamed-since']]);

        $this->expectException(ScreenException::class);
        $this->expectExceptionMessage('target "renamed-since" not found');
        $screens->tree('settings.index');
    }

    #[Test]
    public function a_file_that_is_not_there_throws(): void
    {
        $this->expectException(ScreenException::class);
        $this->expectExceptionMessage('cannot be read');

        (new ScreenRegistry)->register('settings.index', __DIR__.'/Fixtures/screens/missing.json');
    }
}
