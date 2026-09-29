<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Doctor\Checks\UploadSpace;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Admin\Uploads\FreeSpace;
use WebxUi\Admin\Uploads\Upload;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Admin\Uploads\UploadRefused;
use WebxUi\Admin\Uploads\Uploads;

/**
 * Large files a piece at a time (§4 and §11 of the video spec).
 *
 * What is worth a test is what would be quiet if it broke: a piece written at the wrong place
 * gives a file of the right length and the wrong content, a session handed to another
 * administrator is a leak nobody sees, and an abandoned upload that is never swept is a disk
 * that fills up a month later.
 */
final class UploadsTest extends TestCase
{
    private string $directory;

    private ?int $free = null;

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'webx-uploads-'.bin2hex(random_bytes(4));

        $test = $this;
        $this->app->instance(FreeSpace::class, new class($test) extends FreeSpace
        {
            public function __construct(private readonly UploadsTest $test) {}

            public function bytes(string $path): ?int
            {
                return $this->test->freeBytes();
            }
        });

        $this->app->singleton(Uploads::class, fn ($app): Uploads => new Uploads(
            $app->make('config'),
            $app->make(UploadPurposes::class),
            $app->make(FreeSpace::class),
            $this->directory,
        ));

        $this->app->make(UploadPurposes::class)->register(
            'test.video',
            permission: 'media.upload',
            types: ['video/mp4', 'video/webm'],
            maxBytes: 1000,
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function freeBytes(): ?int
    {
        return $this->free;
    }

    #[Test]
    public function pieces_are_appended_until_the_file_is_whole(): void
    {
        $id = $this->start()->assertCreated()
            ->assertJsonPath('data.offset', 0)
            ->assertJsonPath('data.size', 10)
            ->json('data.id');

        $this->assertIsString($id);
        $this->assertGreaterThanOrEqual(Uploads::MIN_CHUNK, $this->start()->json('data.chunk_size'));

        $this->piece($id, 0, 'hello')->assertNoContent()->assertHeader('Upload-Offset', '5');
        $this->piece($id, 5, 'world')->assertNoContent()->assertHeader('Upload-Offset', '10');

        $this->actingAs($this->editor())->call('HEAD', "/api/cms/uploads/{$id}")
            ->assertOk()
            ->assertHeader('Upload-Offset', '10');

        $this->assertSame('helloworld', file_get_contents($this->uploads()->path($id)));
    }

    #[Test]
    public function a_piece_from_the_wrong_offset_is_told_the_real_one(): void
    {
        $id = (string) $this->start()->json('data.id');

        $this->piece($id, 0, 'hello');

        // A retry of the piece that already landed, and one from the future: both are told 5.
        $this->piece($id, 0, 'hello')->assertStatus(409)->assertHeader('Upload-Offset', '5');
        $this->piece($id, 7, 'rld')->assertStatus(409)->assertHeader('Upload-Offset', '5');

        $this->assertSame('hello', file_get_contents($this->uploads()->path($id)));
        $this->assertSame(5, Upload::query()->sole()->offset);
    }

    #[Test]
    public function more_than_the_file_is_long_is_refused(): void
    {
        $id = (string) $this->start()->json('data.id');

        $this->piece($id, 0, 'hello world, and then some')->assertStatus(422);

        $this->assertSame(0, Upload::query()->sole()->offset);
        $this->assertSame('', file_get_contents($this->uploads()->path($id)));
    }

    #[Test]
    public function the_same_file_again_resumes_its_session_and_nobody_else_gets_it(): void
    {
        $id = (string) $this->start()->json('data.id');
        $this->piece($id, 0, 'hello');

        $this->start()->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.offset', 5);

        // The same file from another administrator is their own upload, from nothing.
        $other = $this->start(admin: $this->editor(2))->assertCreated();
        $this->assertNotSame($id, $other->json('data.id'));
        $this->assertSame(0, $other->json('data.offset'));

        // And the first one's id means nothing to them.
        $this->actingAs($this->editor(2))->call('HEAD', "/api/cms/uploads/{$id}")->assertNotFound();
        $this->piece($id, 5, 'world', $this->editor(2))->assertNotFound();
        $this->actingAs($this->editor(2))->deleteJson("/api/cms/uploads/{$id}")->assertNotFound();
    }

    #[Test]
    public function the_purpose_says_who_may_upload_what(): void
    {
        $this->start(purpose: 'nobody.registered')->assertStatus(422)->assertJsonValidationErrors('purpose');
        $this->start(admin: new Editor(['catalog.view']))->assertForbidden();
        $this->start(type: 'image/png')->assertStatus(422)->assertJsonValidationErrors('type');
        $this->start(size: 1001)->assertStatus(422)->assertJsonValidationErrors('size');

        $this->assertSame(0, Upload::query()->count());
    }

    #[Test]
    public function a_file_larger_than_the_free_space_is_refused_at_the_start(): void
    {
        $this->free = 9;

        $this->start()->assertStatus(422)->assertJsonValidationErrors('size');
        $this->assertSame(0, Upload::query()->count());

        $this->free = 10;
        $this->start()->assertCreated();
    }

    #[Test]
    public function cancelling_removes_the_session_and_its_file(): void
    {
        $id = (string) $this->start()->json('data.id');
        $this->piece($id, 0, 'hello');

        $this->actingAs($this->editor())->deleteJson("/api/cms/uploads/{$id}")->assertNoContent();

        $this->assertSame(0, Upload::query()->count());
        $this->assertFileDoesNotExist($this->uploads()->path($id));
    }

    #[Test]
    public function an_expired_session_is_swept_with_its_file(): void
    {
        $old = (string) $this->start()->json('data.id');
        $this->piece($old, 0, 'hello');

        $this->travel(20)->hours();
        $fresh = (string) $this->start(fingerprint: 'other.mp4|10|1')->json('data.id');

        // The TTL counts from the last piece, not from the start.
        $this->travel(5)->hours();

        $this->actingAs($this->editor())->call('HEAD', "/api/cms/uploads/{$old}")->assertNotFound();

        $this->artisan('webx:prune-uploads')->assertSuccessful();

        $this->assertFalse(Upload::query()->whereKey($old)->exists());
        $this->assertFileDoesNotExist($this->uploads()->path($old));
        $this->assertTrue(Upload::query()->whereKey($fresh)->exists());
        $this->assertFileExists($this->uploads()->path($fresh));
    }

    #[Test]
    public function the_sweep_is_on_the_schedule(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(static fn ($event): bool => str_contains((string) $event->command, 'webx:prune-uploads'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
    }

    #[Test]
    public function a_finished_file_is_claimed_once_for_its_own_purpose(): void
    {
        $id = (string) $this->start()->json('data.id');
        $this->piece($id, 0, 'hello');

        try {
            $this->uploads()->claim($id, 'test.video');
            $this->fail('An unfinished upload was handed over.');
        } catch (UploadRefused $refused) {
            $this->assertSame(422, $refused->status);
        }

        $this->piece($id, 5, 'world');

        try {
            $this->uploads()->claim($id, 'catalog.import');
            $this->fail('An upload was handed to a purpose it was not made for.');
        } catch (UploadRefused $refused) {
            $this->assertSame(422, $refused->status);
        }

        try {
            $this->uploads()->claim($id, 'test.video', $this->editor(2));
            $this->fail("Another administrator's upload was handed over.");
        } catch (UploadRefused $refused) {
            $this->assertSame(404, $refused->status);
        }

        $claimed = $this->uploads()->claim($id, 'test.video', $this->editor());

        $this->assertSame('clip.mp4', $claimed->name);
        $this->assertSame(10, $claimed->size);
        $this->assertSame('helloworld', file_get_contents($claimed->path));
        $this->assertSame(0, Upload::query()->count());

        $this->expectException(UploadRefused::class);
        $this->uploads()->claim($id, 'test.video');
    }

    #[Test]
    public function the_advised_piece_fits_what_php_takes(): void
    {
        $eight = 8 * 1048576;

        $this->assertSame($eight, Uploads::advise($eight, '64M', '64M'));
        $this->assertSame((int) floor(2 * 1048576 * 0.9), Uploads::advise($eight, '2M', '8M'));
        $this->assertSame((int) floor(4 * 1048576 * 0.9), Uploads::advise($eight, '1G', '4M'));
        // No limit at all leaves the configured piece; a tiny one never goes below the floor.
        $this->assertSame($eight, Uploads::advise($eight, '0', '0'));
        $this->assertSame(Uploads::MIN_CHUNK, Uploads::advise($eight, '100K', '100K'));
    }

    #[Test]
    public function the_doctor_warns_when_the_disk_holds_less_than_the_largest_upload(): void
    {
        $this->free = 500;
        [$warning] = $this->app->make(UploadSpace::class)->run();
        $this->assertSame(Diagnosis::WARN, $warning->state);

        $this->free = 5000;
        [$fine] = $this->app->make(UploadSpace::class)->run();
        $this->assertSame(Diagnosis::OK, $fine->state);

        $this->app->make(UploadPurposes::class)->forget();
        $this->assertSame([], $this->app->make(UploadSpace::class)->run());
    }

    private function uploads(): Uploads
    {
        return $this->app->make(Uploads::class);
    }

    private function editor(int $id = 1): Editor
    {
        return new Editor(['media.upload'], $id);
    }

    /**
     * @return TestResponse<Response>
     */
    private function start(
        ?Editor $admin = null,
        string $purpose = 'test.video',
        string $type = 'video/mp4',
        int $size = 10,
        string $fingerprint = 'clip.mp4|10|1727600000000',
    ): TestResponse {
        return $this->actingAs($admin ?? $this->editor())->postJson('/api/cms/uploads', [
            'name' => 'clip.mp4',
            'size' => $size,
            'type' => $type,
            'fingerprint' => $fingerprint,
            'purpose' => $purpose,
        ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function piece(string $id, int $offset, string $bytes, ?Editor $admin = null): TestResponse
    {
        return $this->actingAs($admin ?? $this->editor())->call(
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
}
