<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

final class ImageEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAsAdmin();
    }

    #[Test]
    public function an_edit_is_written_over_the_same_key(): void
    {
        $file = $this->picture();
        $key = $file->path;
        $wasHash = $file->hash;

        $this->postJson("/api/cms/media/files/{$file->id}/edit", [
            'crop' => ['x' => 0, 'y' => 0, 'width' => 300, 'height' => 200],
        ])
            ->assertOk()
            ->assertJsonPath('data.width', 300)
            ->assertJsonPath('data.height', 200)
            // The key is the thing an article already points at.
            ->assertJsonPath('data.path', $key)
            ->assertJsonPath('data.has_original', true);

        $file->refresh();

        $this->assertNotSame($wasHash, $file->hash);
        // …and the address changes with the bytes, or a CDN would keep serving the old picture.
        $this->assertStringContainsString('v='.substr($file->hash, 0, 8), $this->url($file));
    }

    #[Test]
    public function the_original_is_kept_once_and_can_be_put_back(): void
    {
        $file = $this->picture();
        $before = $file->width;

        $this->postJson("/api/cms/media/files/{$file->id}/edit", [
            'resize' => ['width' => 100],
        ])->assertOk();

        $original = $file->refresh()->original_path;
        $this->assertNotNull($original);

        // A second edit does not overwrite the copy: the original is the picture as it arrived,
        // not the picture before the last change.
        $this->postJson("/api/cms/media/files/{$file->id}/edit", [
            'rotate' => 90,
        ])->assertOk();

        $this->assertSame($original, $file->refresh()->original_path);

        $this->postJson("/api/cms/media/files/{$file->id}/restore-original")
            ->assertOk()
            ->assertJsonPath('data.width', $before);
    }

    #[Test]
    public function rotating_turns_the_picture_a_quarter_and_swaps_its_sides(): void
    {
        $file = $this->picture();

        $this->postJson("/api/cms/media/files/{$file->id}/edit", ['rotate' => 90])
            ->assertOk()
            ->assertJsonPath('data.width', $file->height)
            ->assertJsonPath('data.height', $file->width);
    }

    #[Test]
    public function previews_cut_before_an_edit_do_not_survive_it(): void
    {
        $file = $this->picture();

        $this->get("/api/cms/media/files/{$file->id}/thumb?w=160&h=160&fit=cover")->assertRedirect();
        $thumbs = fn (): int => count(array_filter(
            Storage::disk('public')->allFiles(),
            static fn (string $path): bool => str_contains($path, '/thumbs/'),
        ));

        $this->assertSame(1, $thumbs());

        $this->postJson("/api/cms/media/files/{$file->id}/edit", ['rotate' => 180])->assertOk();

        $this->assertSame(0, $thumbs());
    }

    #[Test]
    public function a_copy_is_a_second_file_with_its_own_key(): void
    {
        $file = $this->picture();

        $copy = $this->postJson("/api/cms/media/files/{$file->id}/copy")
            ->assertCreated()
            ->assertJsonPath('data.name', $file->name.' (copy)')
            ->json('data');

        $this->assertNotSame($file->path, $copy['path']);
        Storage::disk('public')->assertExists($copy['path']);
        Storage::disk('public')->assertExists($file->path);
    }

    #[Test]
    public function what_is_not_a_picture_cannot_be_edited(): void
    {
        $file = $this->app->make(FileStore::class)->store(
            UploadedFile::fake()->createWithContent('notes.txt', 'words'),
            $this->root(),
        );

        $this->postJson("/api/cms/media/files/{$file->id}/edit", ['rotate' => 90])->assertStatus(422);
    }

    #[Test]
    public function a_finished_picture_is_not_accepted_in_place_of_an_edit(): void
    {
        $file = $this->picture();

        // Nonsense operations are refused rather than quietly ignored, which is what tells the
        // front end it is speaking the wrong protocol.
        $this->postJson("/api/cms/media/files/{$file->id}/edit", ['rotate' => 37])->assertStatus(422);
        $this->postJson("/api/cms/media/files/{$file->id}/edit", ['flip' => 'sideways'])->assertStatus(422);
    }

    private function picture(): MediaFile
    {
        return $this->app->make(FileStore::class)->store(
            UploadedFile::fake()->image('Sofa Oslo.jpg', 600, 400),
            $this->root(),
        );
    }

    private function url(MediaFile $file): string
    {
        return (string) $this->getJson("/api/cms/media/files/{$file->id}")->json('data.url');
    }

    private function root(): MediaDirectory
    {
        return MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
    }
}
