<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Uploads\FreeSpace;
use WebxUi\Admin\Uploads\Upload;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Admin\Uploads\Uploads;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

/**
 * Uploads into Files a piece at a time, through the panel's protocol.
 *
 * What would be quiet if it broke: a resumed upload that skips or repeats bytes is a picture of
 * the right size and the wrong content, a type refused only at the end is a gigabyte sent for
 * nothing, and a file that skips the library's own rules on the way in because it arrived by
 * another door is a hole in them.
 */
final class ChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAsAdmin();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'webx-media-uploads-'.bin2hex(random_bytes(4));

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
    public function a_picture_sent_in_pieces_lands_in_the_library_like_any_upload(): void
    {
        $bytes = $this->picture();
        $id = $this->start('Sofa Oslo.jpg', strlen($bytes), 'image/jpeg')->assertCreated()->json('data.id');
        $half = intdiv(strlen($bytes), 2);

        $this->piece($id, 0, substr($bytes, 0, $half))->assertNoContent();
        $this->piece($id, $half, substr($bytes, $half))->assertNoContent();

        $response = $this->finish($id)->assertCreated();

        // The same pipeline as a multipart upload: a WebP, named for the title, deduplicated.
        $response
            ->assertJsonPath('data.name', 'Sofa Oslo')
            ->assertJsonPath('data.extension', 'webp')
            ->assertJsonPath('data.width', 1200)
            ->assertJsonPath('data.duplicate', false);

        Storage::disk('public')->assertExists((string) $response->json('data.path'));
        $this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.$id.'.part');

        // The session went with the claim: the same id is not a second file.
        $this->finish($id)->assertNotFound();
        $this->assertSame(1, MediaFile::query()->count());
    }

    #[Test]
    public function a_dropped_connection_resumes_from_where_the_server_is(): void
    {
        $bytes = $this->picture();
        $third = intdiv(strlen($bytes), 3);

        $id = $this->start('Sofa Oslo.jpg', strlen($bytes), 'image/jpeg')->json('data.id');
        $this->piece($id, 0, substr($bytes, 0, $third))->assertNoContent();

        // The page was reloaded and the same file chosen again: the session is found by its
        // fingerprint, and it says how much of the file is already there.
        $again = $this->start('Sofa Oslo.jpg', strlen($bytes), 'image/jpeg')->assertOk();
        $this->assertSame($id, $again->json('data.id'));
        $this->assertSame($third, $again->json('data.offset'));

        // A piece from a stale offset is told the real one, rather than written twice.
        $this->piece($id, 0, substr($bytes, 0, $third))
            ->assertStatus(409)
            ->assertHeader('Upload-Offset', (string) $third);

        $this->piece($id, $third, substr($bytes, $third))->assertNoContent();

        $path = (string) $this->finish($id)->assertCreated()->json('data.path');

        // Whole and in order: the pipeline could decode it into the picture it was.
        $this->assertSame([1200, 800], array_slice((array) getimagesizefromstring((string) Storage::disk('public')->get($path)), 0, 2));
    }

    #[Test]
    public function a_type_the_library_does_not_take_is_refused_before_a_byte_is_sent(): void
    {
        $response = $this->start('payload.php', 100, 'application/x-php')->assertStatus(422);

        $response->assertJsonValidationErrors('type');
        // Said in the library's extensions, which is what whoever holds the file can act on.
        $this->assertStringContainsString('jpg', (string) $response->json('message'));
        $this->assertSame(0, Upload::query()->count());
    }

    #[Test]
    public function a_file_the_browser_has_no_type_for_goes_by_its_extension(): void
    {
        config(['webx-media.upload.extensions' => ['txt', 'heic']]);

        $this->start('IMG_0001.HEIC', 100, '')->assertCreated();
    }

    #[Test]
    public function a_file_over_the_size_limit_is_refused_at_the_start(): void
    {
        config(['webx-media.upload.max_size' => 1]);

        $this->start('Notes.txt', 2048, 'text/plain')
            ->assertStatus(422)
            ->assertJsonValidationErrors('size');

        $this->assertSame(0, Upload::query()->count());
    }

    #[Test]
    public function the_content_is_checked_once_it_is_whole(): void
    {
        $bytes = '<?php echo "not a picture";';
        $id = $this->start('innocent.jpg', strlen($bytes), 'image/jpeg')->json('data.id');
        $this->piece($id, 0, $bytes)->assertNoContent();

        $this->finish($id)->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame(0, MediaFile::query()->count());
        $this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.$id.'.part');
    }

    #[Test]
    public function cancelling_leaves_nothing_behind(): void
    {
        $id = $this->start('Notes.txt', 10, 'text/plain')->json('data.id');
        $this->piece($id, 0, 'half')->assertNoContent();

        $this->deleteJson("/api/cms/uploads/{$id}")->assertNoContent();

        $this->assertSame(0, Upload::query()->count());
        $this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.$id.'.part');
        $this->finish($id)->assertNotFound();
    }

    #[Test]
    public function viewing_the_library_is_not_uploading_into_it(): void
    {
        $user = $this->actingAsAdmin(super: false);
        $this->grant($user, ['media.view']);

        $this->start('Notes.txt', 10, 'text/plain')->assertForbidden();
    }

    private function picture(): string
    {
        // Held in a variable: the fake deletes its temporary file once nothing refers to it.
        $fake = UploadedFile::fake()->image('sofa.jpg', 1200, 800);

        return (string) file_get_contents($fake->getRealPath());
    }

    /**
     * @return TestResponse<Response>
     */
    private function start(string $name, int $size, string $type): TestResponse
    {
        return $this->postJson('/api/cms/uploads', [
            'name' => $name,
            'size' => $size,
            'type' => $type,
            'fingerprint' => "{$name}|{$size}|1727600000000",
            'purpose' => FileStore::UPLOAD_PURPOSE,
        ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function piece(string $id, int $offset, string $bytes): TestResponse
    {
        return $this->call(
            'PATCH',
            "/api/cms/uploads/{$id}",
            server: [
                'CONTENT_TYPE' => 'application/offset+octet-stream',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_UPLOAD_OFFSET' => (string) $offset,
            ],
            content: $bytes,
        );
    }

    /**
     * @return TestResponse<Response>
     */
    private function finish(string $id): TestResponse
    {
        return $this->postJson('/api/cms/media/files/chunked', [
            'directory_id' => MediaDirectory::query()->whereNull('parent_id')->firstOrFail()->getKey(),
            'upload' => $id,
        ]);
    }
}
