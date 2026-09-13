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

final class FileEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAsAdmin();
    }

    #[Test]
    public function uploading_answers_with_what_was_stored(): void
    {
        $response = $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            'files' => [UploadedFile::fake()->image('Sofa Oslo.jpg', 1200, 800)],
        ], ['Accept' => 'application/json'])->assertCreated();

        $response
            ->assertJsonPath('data.0.name', 'Sofa Oslo')
            ->assertJsonPath('data.0.type', 'image')
            ->assertJsonPath('data.0.width', 1200)
            ->assertJsonPath('data.0.editable', true)
            ->assertJsonPath('data.0.duplicate', false);

        // The address is worked out, and carries the version a CDN needs to let go of.
        $this->assertStringContainsString('?v=', (string) $response->json('data.0.url'));
        Storage::disk('public')->assertExists((string) $response->json('data.0.path'));
    }

    #[Test]
    public function the_same_file_twice_is_reported_as_the_same_file(): void
    {
        $upload = fn (): UploadedFile => UploadedFile::fake()->createWithContent('picture.jpg', 'the same bytes');

        $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            'files' => [$upload()],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            'files' => [$upload()],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.0.duplicate', true);

        $this->assertSame(1, MediaFile::query()->count());
    }

    #[Test]
    public function a_type_nobody_asked_for_is_refused(): void
    {
        $response = $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            'files' => [UploadedFile::fake()->create('payload.php', 4, 'application/x-php')],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);

        /** @var array<string, list<string>> $errors */
        $errors = $response->json('errors');
        $message = implode(' ', array_merge(...array_values($errors)));

        // In extensions, and only those. Laravel's own message lists every mime type it was
        // given, which arrives as a paragraph of
        // application/vnd.openxmlformats-officedocument… — true, and useless to whoever is
        // holding the file.
        $this->assertStringContainsString('jpg', $message);
        $this->assertStringContainsString('xlsx', $message);
        $this->assertStringNotContainsString('application/', $message);

        $this->assertSame(0, MediaFile::query()->count());
    }

    #[Test]
    public function a_picture_too_large_to_decode_is_refused_before_anything_decodes_it(): void
    {
        config(['webx-media.image.max_pixels' => 10_000]);

        $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            'files' => [UploadedFile::fake()->image('huge.jpg', 400, 400)],
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    #[Test]
    public function the_listing_is_paginated_filtered_and_sorted(): void
    {
        $root = $this->root();
        $this->upload($root, 'Alpha.jpg');
        $this->upload($root, 'Beta.jpg');
        $document = $this->upload($root, 'Notes.txt');

        $this->getJson('/api/cms/media/files?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/cms/media/files?type=document')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $document->id);

        $this->getJson('/api/cms/media/files?sort=name')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha');
    }

    #[Test]
    public function searching_reaches_across_folders(): void
    {
        $folder = new MediaDirectory(['title' => 'Pictures']);
        $folder->appendTo($this->root());

        $this->upload($this->root(), 'Sofa Oslo.jpg');
        $this->upload($folder->refresh(), 'Chair Bergen.jpg');

        $this->getJson('/api/cms/media/files?q=bergen')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Chair Bergen');

        // Case, and a language whose case sqlite's own LIKE does not know about.
        $this->getJson('/api/cms/media/files?q=BERGEN')->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function renaming_leaves_the_key_alone(): void
    {
        $file = $this->upload($this->root(), 'Sofa Oslo.jpg');
        $key = $file->path;

        $this->patchJson("/api/cms/media/files/{$file->id}", ['name' => 'Sofa, grey'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sofa, grey')
            ->assertJsonPath('data.path', $key);

        Storage::disk('public')->assertExists($key);
    }

    #[Test]
    public function files_move_in_a_batch_without_touching_the_bytes(): void
    {
        $folder = new MediaDirectory(['title' => 'Pictures']);
        $folder->appendTo($this->root());

        $first = $this->upload($this->root(), 'One.jpg');
        $second = $this->upload($this->root(), 'Two.jpg');

        $this->postJson('/api/cms/media/files/move', [
            'ids' => [$first->id, $second->id],
            'directory_id' => $folder->refresh()->getKey(),
        ])
            ->assertOk()
            ->assertJsonPath('data.moved', 2);

        $this->assertSame($folder->getKey(), $first->refresh()->directory_id);
        Storage::disk('public')->assertExists($first->path);
    }

    #[Test]
    public function deleting_in_a_batch_takes_the_bytes_too(): void
    {
        $first = $this->upload($this->root(), 'One.jpg');
        $second = $this->upload($this->root(), 'Two.jpg');

        $this->postJson('/api/cms/media/files/delete', ['ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertMissing($second->path);
    }

    #[Test]
    public function a_preview_is_cut_once_and_then_redirected_to(): void
    {
        $file = $this->upload($this->root(), 'Sofa Oslo.jpg');

        $response = $this->get("/api/cms/media/files/{$file->id}/thumb?w=160&h=160&fit=cover");

        $response->assertRedirect();
        $this->assertStringContainsString('thumbs/', (string) $response->headers->get('Location'));

        $before = count(Storage::disk('public')->allFiles());
        $this->get("/api/cms/media/files/{$file->id}/thumb?w=160&h=160&fit=cover")->assertRedirect();

        // The second visit is served from what the first one left behind.
        $this->assertSame($before, count(Storage::disk('public')->allFiles()));
    }

    #[Test]
    public function a_size_nobody_allowed_is_refused(): void
    {
        $file = $this->upload($this->root(), 'Sofa Oslo.jpg');

        $this->getJson("/api/cms/media/files/{$file->id}/thumb?w=137")->assertStatus(422);
    }

    #[Test]
    public function a_document_has_no_preview(): void
    {
        $file = $this->upload($this->root(), 'Notes.txt');

        $this->getJson("/api/cms/media/files/{$file->id}/thumb?w=160")->assertNotFound();
        $this->getJson("/api/cms/media/files/{$file->id}")->assertOk()->assertJsonPath('data.thumb', null);
    }

    #[Test]
    public function uploading_is_not_managing(): void
    {
        $user = $this->actingAsAdmin(super: false);
        $this->grant($user, ['media.view', 'media.upload']);

        $this->post('/api/cms/media/files', [
            'directory_id' => $this->root()->getKey(),
            'files' => [UploadedFile::fake()->image('One.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $file = MediaFile::query()->firstOrFail();

        $this->postJson('/api/cms/media/files/delete', ['ids' => [$file->id]])->assertForbidden();
    }

    #[Test]
    public function searching_does_not_care_about_case_in_any_alphabet(): void
    {
        $this->upload($this->root(), 'Диван Осло.jpg');

        foreach (['диван', 'ДИВАН', 'Осло', 'осло'] as $query) {
            $this->getJson('/api/cms/media/files?q='.urlencode($query))
                ->assertOk()
                ->assertJsonCount(1, 'data');
        }
    }

    #[Test]
    public function the_editor_reads_the_picture_from_the_panel_itself(): void
    {
        $file = $this->upload($this->root(), 'Sofa Oslo.jpg');

        // A CDN in front of the library is another origin, and a canvas drawn from another
        // origin cannot be written out — so the address the editor uses is the panel's own.
        $source = (string) $this->getJson("/api/cms/media/files/{$file->id}")->json('data.source');

        $this->assertStringContainsString("/media/files/{$file->id}/source", $source);

        $response = $this->get($source);

        $response->assertOk();
        $this->assertSame($file->mime, $response->headers->get('Content-Type'));
        // The bytes at this key change when the picture is edited and the address carries no
        // version of its own, so it must not be kept.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function what_is_not_a_picture_has_nothing_to_edit_from(): void
    {
        $file = $this->upload($this->root(), 'Notes.txt');

        $this->getJson("/api/cms/media/files/{$file->id}")->assertOk()->assertJsonPath('data.source', null);
        $this->getJson("/api/cms/media/files/{$file->id}/source")->assertNotFound();
    }

    private function root(): MediaDirectory
    {
        return MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
    }

    private function upload(MediaDirectory $directory, string $name): MediaFile
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        static $nth = 0;
        $nth++;

        $upload = $extension === 'jpg'
            ? UploadedFile::fake()->image($name, 600 + $nth, 400)
            : UploadedFile::fake()->createWithContent($name, 'text '.uniqid());

        return $this->app->make(FileStore::class)->store($upload, $directory);
    }
}
