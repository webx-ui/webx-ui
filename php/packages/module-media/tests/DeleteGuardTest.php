<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Media\Audit\OrphanThumbnailsCheck;
use WebxUi\Media\Audit\PruneThumbnailsFix;
use WebxUi\Media\Images\OrphanThumbnails;
use WebxUi\Media\Images\Thumbnails;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

/**
 * Deleting from the panel keeps the rule `media_delete_files` keeps for an agent — a file the
 * site uses is refused unless `force` says otherwise, and the panel can ask where before it
 * asks the question — and takes the previews with the file, which nothing else ever deletes.
 */
final class DeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAsAdmin();

        Schema::create('test_articles', static function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->foreignId('cover_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->json('content')->nullable();
        });
    }

    #[Test]
    public function the_panel_is_told_where_a_file_is_used_and_by_what_name(): void
    {
        $cover = $this->upload($this->root());
        $loose = $this->upload($this->root());

        DB::table('test_articles')->insert([
            'id' => 5,
            'title' => json_encode(['en' => 'About us', 'ru' => 'О нас']),
            'cover_id' => $cover->id,
        ]);

        $this->postJson('/api/cms/media/files/usage', ['ids' => [$cover->id, $loose->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cover->id)
            ->assertJsonPath('data.0.used_in.0.table', 'test_articles')
            ->assertJsonPath('data.0.used_in.0.id', 5)
            ->assertJsonPath('data.0.used_in.0.label', 'About us');
    }

    #[Test]
    public function a_file_in_use_is_refused_one_at_a_time_and_in_a_batch_unless_forced(): void
    {
        $cover = $this->upload($this->root());
        $inBlock = $this->upload($this->root());
        $loose = $this->upload($this->root());

        DB::table('test_articles')->insert([
            ['id' => 5, 'title' => null, 'cover_id' => $cover->id, 'content' => null],
            ['id' => 6, 'title' => null, 'cover_id' => null, 'content' => json_encode(['image' => ['path' => $inBlock->path]])],
        ]);

        $this->deleteJson("/api/cms/media/files/{$cover->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'files_in_use')
            ->assertJsonPath('in_use.0.used_in.0.column', 'cover_id');

        $this->postJson('/api/cms/media/files/delete', ['ids' => [$inBlock->id, $loose->id]])
            ->assertStatus(409)
            ->assertJsonCount(1, 'in_use')
            ->assertJsonPath('in_use.0.id', $inBlock->id);

        // Refused means nothing went, not the files that happened to be free.
        $this->assertSame(3, MediaFile::query()->count());

        $this->postJson('/api/cms/media/files/delete', ['ids' => [$loose->id]])->assertOk();

        $this->deleteJson("/api/cms/media/files/{$cover->id}?force=1")->assertNoContent();
        $this->postJson('/api/cms/media/files/delete', ['ids' => [$inBlock->id], 'force' => true])
            ->assertOk()
            ->assertJsonPath('data.deleted', 1);

        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function a_folder_says_what_is_inside_and_what_of_it_is_used_through_the_whole_subtree(): void
    {
        $pictures = $this->folder('Pictures', $this->root());
        $covers = $this->folder('Covers', $pictures);
        $this->upload($pictures);
        $deeper = $this->upload($covers);

        DB::table('test_articles')->insert(['id' => 7, 'title' => null, 'cover_id' => $deeper->id]);

        $this->getJson("/api/cms/media/directories/{$pictures->getKey()}/contents")
            ->assertOk()
            ->assertJsonPath('data.files', 2)
            ->assertJsonPath('data.directories', 1)
            ->assertJsonCount(1, 'data.in_use')
            ->assertJsonPath('data.in_use.0.id', $deeper->id);
    }

    #[Test]
    public function the_previews_go_with_the_file_one_at_a_time_in_a_batch_and_with_a_folder(): void
    {
        $folder = $this->folder('Pictures', $this->root());
        $one = $this->upload($this->root());
        $batch = $this->upload($this->root());
        $inFolder = $this->upload($folder);

        $previews = array_map(fn (MediaFile $file): string => $this->preview($file), [$one, $batch, $inFolder]);

        foreach ($previews as $preview) {
            Storage::disk('public')->assertExists($preview);
        }

        $this->deleteJson("/api/cms/media/files/{$one->id}")->assertNoContent();
        $this->postJson('/api/cms/media/files/delete', ['ids' => [$batch->id]])->assertOk();
        $this->deleteJson("/api/cms/media/directories/{$folder->getKey()}?force=1")->assertNoContent();

        foreach ($previews as $preview) {
            Storage::disk('public')->assertMissing($preview);
            Storage::disk('public')->assertMissing(dirname($preview));
        }
    }

    #[Test]
    public function previews_left_by_files_long_gone_are_found_and_swept(): void
    {
        $kept = $this->upload($this->root());
        $keptPreview = $this->preview($kept);

        Storage::disk('public')->put('media/thumbs/0190aaaa-gone/160x160-cover.jpg', 'old');
        Storage::disk('public')->put('media/thumbs/0190bbbb-gone/320.jpg', 'old');

        $orphans = $this->app->make(OrphanThumbnails::class);

        $this->assertEqualsCanonicalizing(
            ['media/thumbs/0190aaaa-gone', 'media/thumbs/0190bbbb-gone'],
            array_column($orphans->find(), 'path'),
        );

        // A dry run says and deletes nothing.
        $this->artisan('webx:media:prune-thumbs', ['--dry-run' => true])
            ->expectsOutputToContain('2 folder(s) would be deleted.')
            ->assertSuccessful();
        Storage::disk('public')->assertExists('media/thumbs/0190aaaa-gone/160x160-cover.jpg');

        // The audit's fix sweeps the same, and leaves the previews of a file that is still there.
        $fix = $this->app->make(PruneThumbnailsFix::class);
        $finding = new Finding(OrphanThumbnailsCheck::ID, 'notice', key: 'thumbs');

        $this->assertTrue($fix->available($finding));
        $this->assertCount(2, $fix->preview($finding)->changes);

        $fix->apply($finding);

        Storage::disk('public')->assertMissing('media/thumbs/0190aaaa-gone');
        Storage::disk('public')->assertMissing('media/thumbs/0190bbbb-gone');
        Storage::disk('public')->assertExists($keptPreview);
        $this->assertFalse($fix->available($finding));

        $this->artisan('webx:media:prune-thumbs')->expectsOutputToContain('No orphaned previews.')->assertSuccessful();
    }

    private function preview(MediaFile $file): string
    {
        return $this->app->make(Thumbnails::class)->variant($file, 160, 160, 'cover');
    }

    private function root(): MediaDirectory
    {
        return MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
    }

    private function folder(string $title, MediaDirectory $parent): MediaDirectory
    {
        $folder = new MediaDirectory(['title' => $title]);
        $folder->appendTo($parent);

        return $folder->refresh();
    }

    private function upload(MediaDirectory $directory): MediaFile
    {
        // The size makes each its own file: the store deduplicates by content within a folder.
        static $nth = 0;
        $nth++;

        return $this->app->make(FileStore::class)->store(
            UploadedFile::fake()->image(uniqid().'.jpg', 100 + $nth, 400),
            $directory,
        );
    }
}
