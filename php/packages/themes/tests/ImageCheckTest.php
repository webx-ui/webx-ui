<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Checks\ImageCheck;

/**
 * Rules 37 and 39 of `RULES.md` in `webx:theme:check`: a picture of a template says its size, and
 * a font of a layer that is not `.woff2` is a note — the page works, the visitor downloads more.
 */
final class ImageCheckTest extends TestCase
{
    #[Test]
    public function a_picture_without_its_size_is_named_by_its_line_and_what_it_misses(): void
    {
        $findings = ImageCheck::check((string) file_get_contents(self::fixture('pictures-theme/views/components/gallery.blade.php')));

        $this->assertSame([4, 5, 7], array_column($findings, 'line'));
        $this->assertStringContainsString('<img> without width and height', $findings[0]['message']);
        $this->assertStringContainsString('<img> without height —', $findings[1]['message']);
        $this->assertStringContainsString('rule 37', $findings[2]['message']);
    }

    #[Test]
    public function an_arrow_inside_an_echo_or_a_condition_is_not_the_end_of_the_tag(): void
    {
        $template = '<img src="{{ $a->b }}" @if ($a->c) class="x" @endif width="1" height="1">';

        $this->assertSame([], ImageCheck::check($template));
    }

    #[Test]
    public function the_check_names_the_pictures_and_notes_the_fonts_without_refusing_for_them(): void
    {
        $this->artisan('webx:theme:check', ['path' => self::fixture('pictures-theme'), '--locales' => 'en'])
            ->expectsOutputToContain('gallery.blade.php:4  <img> without width and height')
            ->expectsOutputToContain('gallery.blade.php:5  <img> without height')
            ->expectsOutputToContain('gallery.blade.php:7  <img> without width and height')
            ->expectsOutputToContain('pictures-theme/theme.json  note: fonts[0]: latin: assets/fonts/old-sans-latin.woff is .woff')
            ->expectsOutputToContain('3 findings')
            ->assertFailed();
    }

    #[Test]
    public function a_woff_font_alone_is_a_note_and_the_chain_passes(): void
    {
        $directory = self::fixture('pictures-theme');
        $view = $directory.'/views/components/gallery.blade.php';
        $kept = (string) file_get_contents($view);

        file_put_contents($view, '<img src="x.png" alt="" width="1" height="1">');

        try {
            $this->artisan('webx:theme:check', ['path' => $directory, '--locales' => 'en'])
                ->expectsOutputToContain('note: fonts[0]: latin: assets/fonts/old-sans-latin.woff is .woff')
                ->assertSuccessful();
        } finally {
            file_put_contents($view, $kept);
        }
    }
}
