<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use ErrorException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Exceptions\RuntimeException as ImageException;
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

    #[Test]
    public function a_picture_no_driver_reads_fails_as_the_decoders_own_failure(): void
    {
        Storage::fake('products');
        Storage::disk('products')->put('catalog/0/1/logo.svg', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"/>');
        Storage::disk('products')->put('catalog/0/1/broken.jpg', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"/>');

        $thumbnails = $this->app->make(Thumbnails::class);

        // GD says what it cannot read with a warning first; with an error handler that makes it an
        // exception, as the framework's does in a console, it stopped every thumbnail of a list.
        set_error_handler(static function (int $level, string $message): never {
            throw new ErrorException($message, 0, $level);
        });

        try {
            foreach (['catalog/0/1/logo.svg', 'catalog/0/1/broken.jpg'] as $path) {
                try {
                    $thumbnails->variantOf('products', $path, 160, 160, 'cover');
                    $this->fail("cut a variant of {$path}");
                } catch (ImageException) {
                    // Every caller answers this with the picture whole.
                }
            }
        } finally {
            restore_error_handler();
        }

        Storage::disk('products')->assertMissing('catalog/0/1/thumbs/logo/160x160-cover.jpg');
    }
}
