<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Images\Optimizing\ImageOptimizer;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

final class OptimizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAsAdmin();
    }

    #[Test]
    public function an_upload_goes_in_smaller_turned_into_webp(): void
    {
        $file = $this->upload($this->photograph(3000, 2000, 'jpg'), 'beach.jpg');

        $this->assertSame('webp', $file->extension);
        $this->assertStringEndsWith('.webp', $file->path);
        $this->assertSame(2560, $file->width, 'The long side comes down to the limit.');
        $this->assertSame(1707, $file->height);
        $this->assertSame(app(ImageOptimizer::class)->signature(), $file->optimized);
        $this->assertSame($file->size, strlen((string) Storage::disk('public')->get($file->path)));
    }

    #[Test]
    public function what_the_pipeline_does_not_take_goes_in_as_it_came(): void
    {
        $gif = $this->upload(UploadedFile::fake()->image('loop.gif', 40, 40), 'loop.gif');
        $pdf = $this->upload(UploadedFile::fake()->create('terms.pdf', 10, 'application/pdf'), 'terms.pdf');

        $this->assertSame('gif', $gif->extension);
        $this->assertNull($gif->optimized);
        $this->assertSame('pdf', $pdf->extension);
    }

    #[Test]
    public function nothing_is_converted_while_the_pipeline_is_off(): void
    {
        config(['webx-media.optimize.enabled' => false]);

        $file = $this->upload($this->photograph(3000, 2000, 'jpg'), 'beach.jpg');

        $this->assertSame('jpg', $file->extension);
        $this->assertSame(3000, $file->width);
        $this->assertNull($file->optimized);
    }

    #[Test]
    public function optimize_writes_an_old_picture_over_its_own_key_in_its_own_format(): void
    {
        $file = $this->oldPicture();
        $key = $file->path;
        $before = $file->size;

        $this->postJson('/api/cms/media/files/optimize/pending', ['directory_id' => $file->directory_id])
            ->assertOk()
            ->assertJsonPath('data.ids', [$file->id]);

        $this->postJson('/api/cms/media/files/optimize', ['ids' => [$file->id]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'optimized')
            ->assertJsonPath('data.0.before', $before);

        $file->refresh();

        // The key is what every page already points at; the format is in the key.
        $this->assertSame($key, $file->path);
        $this->assertSame('jpg', $file->extension);
        $this->assertSame(2560, $file->width);
        $this->assertLessThan($before, $file->size);
        $this->assertSame(md5((string) Storage::disk('public')->get($key)), $file->hash);

        // Done with these settings: the next press has nothing to do, rather than re-encoding
        // it once more and losing a little more each time.
        $this->postJson('/api/cms/media/files/optimize/pending', ['directory_id' => $file->directory_id])
            ->assertOk()
            ->assertJsonPath('data.ids', []);
    }

    #[Test]
    public function new_settings_bring_the_old_pictures_back_into_the_list(): void
    {
        $file = $this->upload($this->photograph(1200, 800, 'jpg'), 'beach.jpg');
        $this->assertSame([], $this->pending($file));

        config(['webx-media.optimize.max_side' => 1000]);

        $this->assertSame([$file->id], $this->pending($file));
    }

    #[Test]
    public function a_picture_the_pipeline_cannot_shrink_is_left_alone_and_remembered(): void
    {
        config(['webx-media.optimize.enabled' => false]);
        // A tiny PNG of one colour: there is nothing left to take out of it.
        $file = $this->upload(UploadedFile::fake()->image('dot.png', 4, 4), 'dot.png');
        $bytes = Storage::disk('public')->get($file->path);
        config(['webx-media.optimize.enabled' => true]);

        $this->postJson('/api/cms/media/files/optimize', ['ids' => [$file->id]])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'unchanged');

        $this->assertSame($bytes, Storage::disk('public')->get($file->path));
        $this->assertSame([], $this->pending($file));
    }

    #[Test]
    public function a_batch_has_a_ceiling(): void
    {
        $this->postJson('/api/cms/media/files/optimize', ['ids' => range(1, 11)])
            ->assertUnprocessable();
    }

    #[Test]
    public function optimizing_is_managing(): void
    {
        $user = $this->actingAsAdmin(super: false);
        $this->grant($user, ['media.view', 'media.upload']);

        $this->postJson('/api/cms/media/files/optimize/pending', [])->assertForbidden();
    }

    /** A JPEG uploaded before there was a pipeline: full size, never optimized. */
    private function oldPicture(): MediaFile
    {
        config(['webx-media.optimize.enabled' => false]);
        $file = $this->upload($this->photograph(3000, 2000, 'jpg'), 'old.jpg');
        config(['webx-media.optimize.enabled' => true]);

        return $file;
    }

    /**
     * @return list<int>
     */
    private function pending(MediaFile $file): array
    {
        return (array) $this->postJson('/api/cms/media/files/optimize/pending', ['directory_id' => $file->directory_id])->json('data.ids');
    }

    private function upload(UploadedFile $upload, string $name): MediaFile
    {
        $directory = MediaDirectory::query()->firstOrCreate(['parent_id' => null, 'title' => 'Library']);

        return app(FileStore::class)->store($upload, $directory);
    }

    /** A noisy picture, so that encoding it costs bytes the way a photograph does. */
    private function photograph(int $width, int $height, string $extension): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);

        for ($i = 0; $i < 4000; $i++) {
            $colour = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), (int) $colour);
        }

        $path = tempnam(sys_get_temp_dir(), 'wx').'.'.$extension;
        imagejpeg($image, $path, 100);

        return new UploadedFile($path, 'photo.'.$extension, 'image/jpeg', null, true);
    }
}
