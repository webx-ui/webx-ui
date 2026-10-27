<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Support\ServeHeaders;
use WebxUi\Media\Support\SvgSanitizer;

/**
 * An SVG is a document that runs scripts on the site's origin; a file's type said by the client
 * is whatever the client wanted. Both are what this test holds the library to.
 */
final class SvgSafetyTest extends TestCase
{
    private const EVIL = <<<'SVG'
        <?xml version="1.0"?>
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10" onload="alert(1)">
          <script>alert(2)</script>
          <defs><path id="line" d="M0 0h10"/></defs>
          <use xlink:href="#line"/>
          <use href="https://example.com/sprite.svg#icon"/>
          <a href="javascript:alert(3)"><rect width="5" height="5" onclick="alert(4)"/></a>
          <a xlink:href=" JaVaScRiPt:alert(5)"><circle r="1"/></a>
          <foreignObject><div xmlns="http://www.w3.org/1999/xhtml" onmouseover="alert(6)">x</div></foreignObject>
          <image href="data:image/png;base64,iVBORw0KGgo="/>
          <image href="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4="/>
          <image href="https://example.com/tracker.png"/>
          <style>@import url(https://example.com/a.css);</style>
          <text x="1" y="2">Hello</text>
        </svg>
        SVG;

    private const CLEAN = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><linearGradient id="g"><stop offset="0" stop-color="#fff"/></linearGradient><rect width="5" height="5" fill="url(#g)"/></svg>';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function scripts_handlers_and_outside_addresses_are_cut_out_and_the_drawing_stays(): void
    {
        $result = $this->app->make(SvgSanitizer::class)->sanitize(self::EVIL);

        $this->assertNotNull($result);
        $this->assertTrue($result->changed());

        foreach (['<script', 'alert', 'javascript', 'onload', 'onclick', 'onmouseover', 'foreignObject', 'example.com', 'image/svg+xml', '@import'] as $gone) {
            $this->assertStringNotContainsStringIgnoringCase($gone, $result->contents);
        }

        // What makes it a picture is still there: shapes, text, a reference inside the document,
        // and an inline raster.
        $this->assertStringContainsString('xlink:href="#line"', $result->contents);
        $this->assertStringContainsString('data:image/png;base64', $result->contents);
        $this->assertStringContainsString('<text x="1" y="2">Hello</text>', $result->contents);

        $this->assertContains('<script>', $result->removed);
        $this->assertContains('<svg onload>', $result->removed);
        $this->assertContains('<rect onclick>', $result->removed);
        $this->assertContains('<foreignobject>', $result->removed);
    }

    #[Test]
    public function a_clean_drawing_loses_nothing_and_a_second_pass_changes_nothing(): void
    {
        $sanitizer = $this->app->make(SvgSanitizer::class);

        $clean = $sanitizer->sanitize(self::CLEAN);
        $this->assertNotNull($clean);
        $this->assertFalse($clean->changed());

        $once = $sanitizer->sanitize(self::EVIL);
        $this->assertNotNull($once);
        $twice = $sanitizer->sanitize($once->contents);
        $this->assertNotNull($twice);
        $this->assertFalse($twice->changed());
        $this->assertSame($once->contents, $twice->contents);
    }

    #[Test]
    public function what_is_not_a_well_formed_drawing_is_refused_rather_than_repaired(): void
    {
        $sanitizer = $this->app->make(SvgSanitizer::class);

        $this->assertNull($sanitizer->sanitize('<svg xmlns="http://www.w3.org/2000/svg"><rect></svg>'));
        $this->assertNull($sanitizer->sanitize('<html><body><script>alert(1)</script></body></html>'));
        $this->assertNull($sanitizer->sanitize('<!DOCTYPE svg [<!ENTITY a "aaaa">]><svg xmlns="http://www.w3.org/2000/svg"><text>&a;</text></svg>'));
        $this->assertNull($sanitizer->sanitize(''));
    }

    #[Test]
    public function an_uploaded_svg_is_kept_sanitized_with_the_type_the_server_read(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            // The client calls it a page; the server does not ask.
            'files' => [$this->real('logo.svg', self::EVIL, 'text/html')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $file = MediaFile::query()->findOrFail($response->json('data.0.id'));

        $this->assertSame('svg', $file->extension);
        $this->assertSame('image/svg+xml', $file->mime);

        $kept = (string) Storage::disk('public')->get($file->path);
        $this->assertStringNotContainsString('<script', $kept);
        $this->assertStringNotContainsString('onload', $kept);
        $this->assertSame(strlen($kept), $file->size);
        $this->assertSame(md5($kept), $file->hash);
    }

    #[Test]
    public function a_page_dressed_as_an_svg_is_refused(): void
    {
        $this->actingAsAdmin();

        $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            'files' => [$this->real('logo.svg', '<html><body><script>alert(1)</script></body></html>', 'image/svg+xml')],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function text_that_is_not_a_drawing_is_refused_as_an_svg(): void
    {
        $this->actingAsAdmin();

        $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            'files' => [$this->real('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect></svg>', 'image/svg+xml')],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0' => __('webx-media::errors.unsafe-svg')]);
    }

    #[Test]
    public function the_extension_and_type_come_from_the_bytes_not_from_the_client(): void
    {
        $this->actingAsAdmin();

        // Plain text named and typed as a page: kept as what it is, never under `.html`.
        $response = $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            'files' => [$this->real('notes.html', "Plain notes, nothing more.\n", 'text/html')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $file = MediaFile::query()->findOrFail($response->json('data.0.id'));

        $this->assertSame('txt', $file->extension);
        $this->assertSame('text/plain', $file->mime);
        $this->assertStringEndsWith('.txt', $file->path);
    }

    #[Test]
    public function a_caller_that_skips_the_rules_still_gets_a_sanitized_svg(): void
    {
        $file = $this->app->make(FileStore::class)->store($this->real('icon.svg', self::EVIL, 'image/png'), $this->root());

        $this->assertSame('image/svg+xml', $file->mime);
        $this->assertStringNotContainsString('<script', (string) Storage::disk('public')->get($file->path));
    }

    #[Test]
    public function an_svg_served_by_the_panel_can_draw_and_do_nothing_else(): void
    {
        $this->actingAsAdmin();

        $file = $this->app->make(FileStore::class)->store($this->real('icon.svg', self::CLEAN, 'image/svg+xml'), $this->root());

        $response = $this->get("/api/cms/media/files/{$file->id}/source")->assertOk();

        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame(ServeHeaders::SVG_POLICY, $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function every_user_file_is_nosniff_and_a_type_that_renders_as_a_page_is_a_download(): void
    {
        $this->assertSame(['X-Content-Type-Options' => 'nosniff'], ServeHeaders::for('image/png'));
        $this->assertSame('inline', ServeHeaders::disposition('image/png'));
        $this->assertSame('inline', ServeHeaders::disposition('application/pdf'));
        $this->assertSame('attachment', ServeHeaders::disposition('text/html'));
        $this->assertSame('attachment', ServeHeaders::disposition('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertArrayHasKey('Content-Security-Policy', ServeHeaders::for('image/svg+xml'));
    }

    #[Test]
    public function the_command_sanitizes_what_was_uploaded_before_and_says_what_went(): void
    {
        $evil = $this->legacy('media/aa/bb/evil.svg', self::EVIL, 'text/html');
        $clean = $this->legacy('media/cc/dd/clean.svg', self::CLEAN, 'image/svg+xml');
        $page = $this->legacy('media/ee/ff/page.html', '<p>hi</p>', 'text/html', 'html');

        // Through Artisan rather than `artisan()`: the test double for the console reads `<script>`
        // in the report as markup.
        $this->assertSame(0, Artisan::call('webx:media:sanitize-svg', ['--dry-run' => true]));
        $report = Artisan::output();

        $this->assertStringContainsString("#{$evil->id} media/aa/bb/evil.svg: removed <svg onload>, <script>", $report);
        $this->assertStringContainsString("#{$page->id} media/ee/ff/page.html: kept as .html", $report);
        $this->assertStringNotContainsString('clean.svg', $report);

        // A dry run writes nothing.
        $this->assertSame(self::EVIL, Storage::disk('public')->get('media/aa/bb/evil.svg'));
        $this->assertSame('text/html', $evil->refresh()->mime);

        $this->artisan('webx:media:sanitize-svg')->assertSuccessful();

        $kept = (string) Storage::disk('public')->get('media/aa/bb/evil.svg');
        $this->assertStringNotContainsString('<script', $kept);
        $this->assertSame('image/svg+xml', $evil->refresh()->mime);
        // The version in the address moves on, so a CDN lets go of the old bytes.
        $this->assertSame(md5($kept), $evil->hash);

        $this->assertSame(self::CLEAN, Storage::disk('public')->get('media/cc/dd/clean.svg'));
        $this->assertSame(md5(self::CLEAN), $clean->refresh()->hash);
    }

    private function real(string $name, string $contents, string $clientType): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'webx-media-test');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $clientType, null, true);
    }

    private function legacy(string $path, string $contents, string $mime, string $extension = 'svg'): MediaFile
    {
        Storage::disk('public')->put($path, $contents);

        return MediaFile::query()->create([
            'directory_id' => $this->root()->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => md5($contents),
            'name' => pathinfo($path, PATHINFO_FILENAME),
            'file_name' => basename($path),
            'extension' => $extension,
            'mime' => $mime,
            'size' => strlen($contents),
        ]);
    }

    private function root(): MediaDirectory
    {
        return MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
    }
}
