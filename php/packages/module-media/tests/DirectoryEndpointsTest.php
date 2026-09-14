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

final class DirectoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function the_tree_comes_back_nested_with_its_counts(): void
    {
        $this->actingAsAdmin();

        $pictures = $this->folder('Pictures', $this->root());
        $this->folder('Covers', $pictures);
        $this->upload($pictures);

        $this->getJson('/api/cms/media/directories')
            ->assertOk()
            ->assertJsonPath('data.0.is_root', true)
            ->assertJsonPath('data.0.children.0.title', 'Pictures')
            ->assertJsonPath('data.0.children.0.files_count', 1)
            ->assertJsonPath('data.0.children.0.children.0.title', 'Covers')
            ->assertJsonPath('data.0.children.0.children.0.depth', 2);
    }

    #[Test]
    public function a_folder_is_created_renamed_and_moved(): void
    {
        $this->actingAsAdmin();

        $created = $this->postJson('/api/cms/media/directories', [
            'parent_id' => $this->root()->getKey(),
            'title' => 'Pictures',
        ])->assertCreated()->json('data');

        $this->patchJson("/api/cms/media/directories/{$created['id']}", ['title' => 'Photographs'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Photographs');

        $target = $this->folder('Archive', $this->root());

        $this->patchJson("/api/cms/media/directories/{$created['id']}/move", [
            'parent_id' => $target->getKey(),
        ])
            ->assertOk()
            ->assertJsonPath('data.parent_id', $target->getKey())
            ->assertJsonPath('data.depth', 2);
    }

    #[Test]
    public function a_folder_cannot_be_moved_into_itself(): void
    {
        $this->actingAsAdmin();

        $pictures = $this->folder('Pictures', $this->root());
        $covers = $this->folder('Covers', $pictures);

        $this->patchJson("/api/cms/media/directories/{$pictures->getKey()}/move", [
            'parent_id' => $covers->getKey(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'directory_into_itself');
    }

    #[Test]
    public function deleting_a_folder_with_something_in_it_asks_first(): void
    {
        $this->actingAsAdmin();

        $pictures = $this->folder('Pictures', $this->root());
        $this->folder('Covers', $pictures);
        $this->upload($pictures);

        $this->deleteJson("/api/cms/media/directories/{$pictures->getKey()}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'directory_not_empty')
            ->assertJsonPath('counts.files', 1)
            ->assertJsonPath('counts.directories', 1);

        $this->assertDatabaseCount('media_directories', 3);
    }

    #[Test]
    public function forcing_it_takes_the_subtree_and_the_bytes(): void
    {
        $this->actingAsAdmin();

        $pictures = $this->folder('Pictures', $this->root());
        $covers = $this->folder('Covers', $pictures);
        $here = $this->upload($pictures);
        $deeper = $this->upload($covers);

        $this->deleteJson("/api/cms/media/directories/{$pictures->getKey()}?force=1")
            ->assertNoContent();

        $this->assertDatabaseCount('media_directories', 1);
        $this->assertSame(0, MediaFile::query()->count());

        // The point of doing this through the service rather than through the cascade: the bytes
        // go too, including the ones a folder deeper.
        Storage::disk('public')->assertMissing($here->path);
        Storage::disk('public')->assertMissing($deeper->path);
    }

    #[Test]
    public function the_root_stays(): void
    {
        $this->actingAsAdmin();

        $root = $this->root();

        $this->deleteJson("/api/cms/media/directories/{$root->getKey()}?force=1")
            ->assertStatus(422)
            ->assertJsonPath('code', 'root_immutable');

        $this->patchJson("/api/cms/media/directories/{$root->getKey()}/move", [
            'parent_id' => $this->folder('Pictures', $root)->getKey(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'root_immutable');
    }

    #[Test]
    public function a_guest_gets_nothing(): void
    {
        $this->getJson('/api/cms/media/directories')->assertUnauthorized();
    }

    #[Test]
    public function looking_is_not_managing(): void
    {
        $reader = $this->actingAsAdmin(super: false);
        $this->grant($reader, ['media.view']);

        $this->getJson('/api/cms/media/directories')->assertOk();

        $this->postJson('/api/cms/media/directories', [
            'parent_id' => $this->root()->getKey(),
            'title' => 'Pictures',
        ])->assertForbidden();
    }

    #[Test]
    public function the_answer_is_worded_in_the_language_that_was_asked_for(): void
    {
        $this->actingAsAdmin();

        $pictures = $this->folder('Pictures', $this->root());
        $this->upload($pictures);

        $this->withHeader('X-Webx-Locale', 'ru')
            ->deleteJson("/api/cms/media/directories/{$pictures->getKey()}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Папка не пуста.');
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
        // A unique name buys nothing: a fake image is a blank canvas of the size asked for, and
        // the store deduplicates by content inside a directory. The size is what makes it its own
        // file — today no test puts two of these in one folder, and this is why none has to know.
        static $nth = 0;
        $nth++;

        return $this->app->make(FileStore::class)->store(
            UploadedFile::fake()->image(uniqid().'.jpg', 100 + $nth, 400),
            $directory,
        );
    }
}
