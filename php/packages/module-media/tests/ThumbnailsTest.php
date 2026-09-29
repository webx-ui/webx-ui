<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Images\Thumbnails;

/**
 * Previews of a picture that is not in the library — a product photo on a disk of its own — cut
 * from where it lies and kept beside it.
 */
final class ThumbnailsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_picture_named_by_its_disk_and_path_gets_previews_beside_it(): void
    {
        Storage::fake('products');
        $picture = UploadedFile::fake()->image('front.jpg', 800, 600);
        Storage::disk('products')->put('catalog/0/42/abc.jpg', (string) file_get_contents($picture->getRealPath()));

        $thumbnails = $this->app->make(Thumbnails::class);

        $path = $thumbnails->variantOf('products', 'catalog/0/42/abc.jpg', 160, 160, 'cover');

        $this->assertSame('catalog/0/42/thumbs/abc/160x160-cover.jpg', $path);
        Storage::disk('products')->assertExists($path);
        $this->assertSame([160, 160], array_slice((array) getimagesizefromstring((string) Storage::disk('products')->get($path)), 0, 2));

        // Asked again, it is the same file rather than a second cut.
        $this->assertSame($path, $thumbnails->variantOf('products', 'catalog/0/42/abc.jpg', 160, 160, 'cover'));

        $thumbnails->forgetOf('products', 'catalog/0/42/abc.jpg');

        Storage::disk('products')->assertMissing($path);
        Storage::disk('products')->assertExists('catalog/0/42/abc.jpg');
    }
}
