<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Exceptions\ModuleException;
use WebxUi\Admin\Tests\Fixtures\MediaModule;
use WebxUi\Admin\Tests\Fixtures\PagesModule;

final class ModuleRegistryTest extends TestCase
{
    #[Test]
    public function modules_come_back_in_navigation_order(): void
    {
        // pages has order 10, media takes the default 0.
        $this->register(new PagesModule, new MediaModule);

        $this->assertSame(
            ['media-library', 'pages'],
            array_map(fn ($module) => $module->id(), $this->registry()->all()),
        );
    }

    #[Test]
    public function two_modules_cannot_share_an_id(): void
    {
        $this->register(new PagesModule);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('[pages] is already registered');

        $this->register(new PagesModule);
    }

    #[Test]
    public function an_unknown_id_is_an_error_rather_than_null(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('No module is registered under the id [nope]');

        $this->registry()->get('nope');
    }

    #[Test]
    public function it_answers_about_what_it_holds(): void
    {
        $this->assertSame(0, $this->registry()->count());
        $this->assertFalse($this->registry()->has('pages'));

        $this->register(new PagesModule);

        $this->assertSame(1, $this->registry()->count());
        $this->assertTrue($this->registry()->has('pages'));
        $this->assertSame('pages', $this->registry()->get('pages')->id());
    }

    #[Test]
    public function the_defaults_fill_in_everything_but_the_id(): void
    {
        $module = new MediaModule;

        $this->assertSame('Media Library', $module->title());
        $this->assertNull($module->icon());
        $this->assertSame(0, $module->order());
        $this->assertSame([], $module->permissions());
        $this->assertSame([], $module->manifest());
    }
}
