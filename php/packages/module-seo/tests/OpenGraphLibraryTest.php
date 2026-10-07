<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Seo\Contracts\SharesImages;
use WebxUi\Seo\Rendering\Images\LibraryImages;

/**
 * `og:image` from the media library's own record: the type and the size it knows, a 1200×630
 * variant for a landscape photo big enough, and an SVG refused by what the file is rather than
 * by what its address looks like.
 */
final class OpenGraphLibraryTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [NestedSetServiceProvider::class, ...parent::getPackageProviders($app), MediaServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function the_library_is_asked_when_it_is_running(): void
    {
        $this->assertInstanceOf(LibraryImages::class, app(SharesImages::class));
    }

    #[Test]
    public function a_landscape_photo_is_shared_as_a_variant_of_the_recommended_size(): void
    {
        $file = $this->file('media/ab/cd/photo.jpg', 'image/jpeg', 1600, 900, UploadedFile::fake()->image('photo.jpg', 1600, 900));

        $image = app(SharesImages::class)->share(Storage::disk('public')->url($file->path).'?v=1');

        $this->assertNotNull($image);
        $this->assertStringContainsString('1200x630-cover.jpg', $image->url);
        $this->assertSame(['image/jpeg', 1200, 630], [$image->type, $image->width, $image->height]);
    }

    #[Test]
    public function a_small_or_portrait_picture_is_shared_as_it_is_with_its_own_size(): void
    {
        $file = $this->file('media/ab/cd/portrait.webp', 'image/webp', 1067, 1600);
        $url = Storage::disk('public')->url($file->path).'?v=1';

        $image = app(SharesImages::class)->share($url);

        $this->assertNotNull($image);
        $this->assertSame([$url, 'image/webp', 1067, 1600], [$image->url, $image->type, $image->width, $image->height]);
    }

    #[Test]
    public function an_svg_is_refused_by_what_the_file_is(): void
    {
        // A key without the extension: the record's mime is what says it is a vector.
        $file = $this->file('media/ab/cd/logo', 'image/svg+xml', null, null);

        $this->assertNull(app(SharesImages::class)->share(Storage::disk('public')->url($file->path)));
    }

    #[Test]
    public function a_picture_the_library_does_not_hold_is_described_by_its_address(): void
    {
        $image = app(SharesImages::class)->share('https://cdn.example.test/products/42.png');

        $this->assertNotNull($image);
        $this->assertSame(['image/png', null], [$image->type, $image->width]);
    }

    private function file(string $path, string $mime, ?int $width, ?int $height, ?UploadedFile $bytes = null): MediaFile
    {
        if ($bytes !== null) {
            Storage::disk('public')->put($path, (string) file_get_contents($bytes->getRealPath()));
        }

        /** @var MediaDirectory $root */
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => $path,
            'hash' => md5($path),
            'name' => basename($path),
            'file_name' => basename($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'mime' => $mime,
            'size' => 1000,
            'width' => $width,
            'height' => $height,
        ]);
    }
}
