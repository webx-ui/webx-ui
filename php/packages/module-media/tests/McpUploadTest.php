<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Uploads\FreeSpace;
use WebxUi\Admin\Uploads\Upload;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Admin\Uploads\Uploads;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Media\MediaModule;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * `media_upload_file`: bytes the agent holds, whole or a piece at a time.
 *
 * What would be quiet if it broke: a file that skips the panel's rules because it came by
 * another door (an SVG with its script, a type the library does not take, a file over the
 * limit), a piece appended twice, and a dry run that stores after all.
 */
final class McpUploadTest extends TestCase
{
    private string $directory;

    private CmsUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = $this->actingAsAdmin();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'webx-media-mcp-'.bin2hex(random_bytes(4));

        $this->app->instance(FreeSpace::class, new class extends FreeSpace
        {
            public function bytes(string $path): ?int
            {
                return null;
            }
        });

        $this->app->singleton(Uploads::class, fn ($app): Uploads => new Uploads(
            $app->make('config'),
            $app->make(UploadPurposes::class),
            $app->make(FreeSpace::class),
            $this->directory,
        ));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function a_picture_sent_as_its_content_lands_in_the_folder_like_any_upload(): void
    {
        $answer = $this->invoke([
            'content' => base64_encode($this->picture()),
            'name' => 'Sofa Oslo.jpg',
            'directory_id' => $this->root()->id,
            'alt' => 'A grey sofa',
        ]);

        $file = MediaFile::query()->findOrFail($answer['id']);

        // What media_get_file answers, so the path goes straight into a media field.
        $this->assertTrue($answer['ok']);
        $this->assertFalse($answer['duplicate']);
        $this->assertSame($file->path, $answer['path']);
        $this->assertSame('image', $answer['type']);
        $this->assertSame(1200, $answer['width']);
        $this->assertSame(800, $answer['height']);
        $this->assertNotEmpty($answer['url']);
        $this->assertSame(['en' => 'A grey sofa'], $answer['alt']);
        $this->assertSame($this->root()->id, $file->directory_id);
        Storage::disk('public')->assertExists($file->path);

        // The panel's pipeline: re-encoded, named for the title.
        $this->assertSame('image/webp', $file->mime);
        $this->assertSame('Sofa Oslo', $file->name);
    }

    #[Test]
    public function the_same_bytes_twice_are_the_same_file(): void
    {
        $content = 'data:image/png;base64,'.base64_encode($this->png());

        $first = $this->invoke(['content' => $content, 'name' => 'dot.png', 'directory_id' => $this->root()->id]);
        $second = $this->invoke(['content' => $content, 'name' => 'dot.png', 'directory_id' => $this->root()->id]);

        $this->assertSame($first['id'], $second['id']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, MediaFile::query()->count());
    }

    #[Test]
    public function a_video_goes_in_as_it_is(): void
    {
        $bytes = $this->mp4();

        $answer = $this->invoke(['content' => base64_encode($bytes), 'name' => 'clip.mp4', 'directory_id' => $this->root()->id]);

        $this->assertSame('video', $answer['type']);
        $this->assertSame('video/mp4', $answer['mime']);
        $this->assertSame($bytes, Storage::disk('public')->get($answer['path']));
    }

    #[Test]
    public function the_type_is_what_the_bytes_are(): void
    {
        // A picture named like a script is kept as the picture it is.
        $answer = $this->invoke(['content' => base64_encode($this->png()), 'name' => 'shell.php', 'directory_id' => $this->root()->id]);

        $this->assertSame('image', $answer['type']);
        $this->assertStringEndsNotWith('.php', $answer['path']);

        $this->assertRefused(
            ['content' => base64_encode('<?php echo "not a picture";'), 'name' => 'photo.jpg', 'directory_id' => $this->root()->id],
            'Only these can be uploaded',
        );
    }

    #[Test]
    public function a_type_the_library_does_not_take_is_refused(): void
    {
        $this->app['config']->set('webx-media.upload.extensions', ['jpg', 'jpeg', 'webp']);

        $this->assertRefused(['content' => base64_encode($this->png()), 'name' => 'dot.png', 'directory_id' => $this->root()->id], 'Only these can be uploaded');
        $this->assertRefused(['step' => 'start', 'name' => 'dot.png', 'size' => 100], 'jpg, jpeg, webp');
        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function a_file_over_the_size_limit_is_refused(): void
    {
        $this->app['config']->set('webx-media.upload.max_size', 1);

        $this->assertRefused(
            ['content' => base64_encode($this->picture()), 'name' => 'sofa.jpg', 'directory_id' => $this->root()->id],
            'The file is larger than',
        );
        $this->assertRefused(['step' => 'start', 'name' => 'sofa.jpg', 'size' => 4096], 'larger than');
        $this->assertSame(0, Upload::query()->count());
    }

    #[Test]
    public function a_call_larger_than_a_piece_is_told_to_send_pieces(): void
    {
        $piece = intdiv($this->app->make(Uploads::class)->chunkSize(), 4) * 3;

        $this->assertRefused(
            ['content' => base64_encode(random_bytes($piece + 1)), 'name' => 'big.zip', 'directory_id' => $this->root()->id],
            'Send it in pieces',
        );
    }

    #[Test]
    public function an_svg_keeps_no_script(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)"><script>alert(2)</script><rect width="10" height="10"/></svg>';

        $answer = $this->invoke(['content' => base64_encode($svg), 'name' => 'logo.svg', 'directory_id' => $this->root()->id]);
        $kept = (string) Storage::disk('public')->get($answer['path']);

        $this->assertStringContainsString('<rect', $kept);
        $this->assertStringNotContainsString('<script', $kept);
        $this->assertStringNotContainsString('onload', $kept);

        // One that is not an SVG at all is refused, the way the panel refuses it.
        $this->assertRefused(
            ['content' => base64_encode('<html><body><script>alert(1)</script></body></html>'), 'name' => 'logo.svg', 'directory_id' => $this->root()->id],
            'Only these can be uploaded',
        );
        $this->assertSame(1, MediaFile::query()->count());
    }

    #[Test]
    public function a_large_file_goes_in_pieces(): void
    {
        $bytes = $this->picture();
        $half = intdiv(strlen($bytes), 2);

        $started = $this->invoke(['step' => 'start', 'name' => 'Sofa Oslo.jpg', 'size' => strlen($bytes)]);

        $this->assertSame(0, $started['offset']);
        $this->assertFalse($started['resumed']);
        $this->assertGreaterThan(0, $started['piece']);

        $first = $this->invoke(['step' => 'append', 'upload' => $started['upload'], 'offset' => 0, 'content' => base64_encode(substr($bytes, 0, $half))]);
        $this->assertSame($half, $first['offset']);
        $this->assertSame(strlen($bytes) - $half, $first['remaining']);

        // A piece sent again is refused with where the server is, not appended twice.
        $this->assertRefused(
            ['step' => 'append', 'upload' => $started['upload'], 'offset' => 0, 'content' => base64_encode(substr($bytes, 0, $half))],
            "starts at offset {$half}",
        );

        // Lost the id: a start for the same name and size carries on.
        $again = $this->invoke(['step' => 'start', 'name' => 'Sofa Oslo.jpg', 'size' => strlen($bytes)]);
        $this->assertSame($started['upload'], $again['upload']);
        $this->assertSame($half, $again['offset']);
        $this->assertTrue($again['resumed']);

        $this->assertRefused(['step' => 'finish', 'upload' => $started['upload'], 'directory_id' => $this->root()->id], 'not finished');

        $this->invoke(['step' => 'append', 'upload' => $started['upload'], 'offset' => $half, 'content' => base64_encode(substr($bytes, $half))]);

        $answer = $this->invoke(['step' => 'finish', 'upload' => $started['upload'], 'directory_id' => $this->root()->id, 'alt' => ['en' => 'A grey sofa']]);

        $this->assertSame('image', $answer['type']);
        $this->assertSame(1200, $answer['width']);
        $this->assertSame(['en' => 'A grey sofa'], $answer['alt']);
        $this->assertSame('Sofa Oslo', $answer['name']);
        $this->assertSame(0, Upload::query()->count());
        $this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.$started['upload'].'.part');
    }

    #[Test]
    public function the_pieces_of_one_administrator_are_not_another_ones(): void
    {
        $bytes = $this->png();
        $started = $this->invoke(['step' => 'start', 'name' => 'dot.png', 'size' => strlen($bytes)]);
        $this->invoke(['step' => 'append', 'upload' => $started['upload'], 'offset' => 0, 'content' => base64_encode($bytes)]);

        $other = $this->actingAsAdmin();

        $this->assertRefused(['step' => 'finish', 'upload' => $started['upload'], 'directory_id' => $this->root()->id], '', $other);
        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function a_dry_run_checks_and_stores_nothing(): void
    {
        $answer = $this->invoke(['content' => base64_encode($this->png()), 'name' => 'dot', 'directory_id' => $this->root()->id, 'dry_run' => true]);

        $this->assertTrue($answer['ok']);
        $this->assertStringContainsString('dot.png', $answer['would']);
        $this->assertSame(0, MediaFile::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());

        // Refused the way the real call would be.
        $this->assertRefused(
            ['content' => base64_encode('<?php echo 1;'), 'name' => 'a.jpg', 'directory_id' => $this->root()->id, 'dry_run' => true],
            'Only these can be uploaded',
        );

        $start = $this->invoke(['step' => 'start', 'name' => 'dot.png', 'size' => 10, 'dry_run' => true]);
        $this->assertArrayHasKey('would', $start);
        $this->assertSame(0, Upload::query()->count());

        $bytes = $this->png();
        $started = $this->invoke(['step' => 'start', 'name' => 'dot.png', 'size' => strlen($bytes)]);
        $this->invoke(['step' => 'append', 'upload' => $started['upload'], 'offset' => 0, 'content' => base64_encode($bytes), 'dry_run' => true]);
        $this->assertSame(0, Upload::query()->findOrFail($started['upload'])->offset);

        $this->invoke(['step' => 'append', 'upload' => $started['upload'], 'offset' => 0, 'content' => base64_encode($bytes)]);
        $finish = $this->invoke(['step' => 'finish', 'upload' => $started['upload'], 'directory_id' => $this->root()->id, 'dry_run' => true]);
        $this->assertStringContainsString('dot.png', $finish['would']);
        $this->assertSame(1, Upload::query()->count());
        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function what_is_missing_is_said(): void
    {
        $this->assertRefused(['content' => 'not base64!', 'name' => 'a.png', 'directory_id' => $this->root()->id], 'base64');
        $this->assertRefused(['content' => base64_encode($this->png()), 'name' => 'a.png', 'directory_id' => 999], 'There is no folder with id 999');
        $this->assertRefused(['content' => base64_encode($this->png()), 'directory_id' => $this->root()->id], '`name` is required');
        $this->assertRefused(['step' => 'start', 'name' => 'a.png'], '`size`');
        $this->assertRefused(['step' => 'append', 'upload' => 'nope', 'content' => 'AA=='], '`offset`');
        $this->assertRefused(['step' => 'rewind'], 'start, append or finish');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function invoke(array $arguments, ?CmsUser $user = null): array
    {
        foreach ($this->app->make(MediaModule::class)->mcpTools() as $tool) {
            if ($tool->name === 'upload_file') {
                /** @var array<string, mixed> $result */
                $result = ($tool->handler)($arguments, $user ?? $this->admin);

                return $result;
            }
        }

        $this->fail('No tool named [upload_file].');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function assertRefused(array $arguments, string $says, ?CmsUser $user = null): void
    {
        try {
            $this->invoke($arguments, $user);
        } catch (ToolFailure $failure) {
            $this->assertStringContainsString($says, $failure->getMessage());

            return;
        }

        $this->fail('The call was not refused.');
    }

    private function picture(): string
    {
        // Held in a variable: the fake deletes its temporary file once nothing refers to it.
        $fake = UploadedFile::fake()->image('sofa.jpg', 1200, 800);

        return (string) file_get_contents($fake->getRealPath());
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** The smallest MP4 the type sniffing recognises: an `ftyp` box and an empty `mdat`. */
    private function mp4(): string
    {
        return pack('N', 24).'ftypisom'.pack('N', 512).'isomiso2'.pack('N', 8).'mdat';
    }

    private function root(): MediaDirectory
    {
        return MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
    }
}
