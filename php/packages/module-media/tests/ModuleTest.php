<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use PHPUnit\Framework\Attributes\Test;

final class ModuleTest extends TestCase
{
    #[Test]
    public function the_panel_is_told_about_the_module(): void
    {
        $this->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.modules.0.id', 'media')
            ->assertJsonPath('data.modules.0.icon', 'folder')
            ->assertJsonPath('data.modules.0.permissions', [
                'media.view',
                'media.upload',
                'media.manage',
            ]);
    }

    #[Test]
    public function the_section_is_named_in_the_language_being_asked_for(): void
    {
        // The header rather than `setLocale`: the panel's own middleware decides the language of
        // every answer, and it would overwrite anything set beforehand.
        $this->withHeader('X-Webx-Locale', 'ru')
            ->getJson('/api/cms/manifest')
            ->assertOk()
            ->assertJsonPath('data.modules.0.title', 'Файлы');
    }

    #[Test]
    public function the_disk_is_the_modules_own_setting(): void
    {
        // Not `filesystems.default`: a site whose own storage is local may still keep its
        // library on S3, and a client installation usually has only `public`.
        $this->assertSame('public', config('webx-media.disk'));
        $this->assertSame('media', config('webx-media.prefix'));
    }
}
