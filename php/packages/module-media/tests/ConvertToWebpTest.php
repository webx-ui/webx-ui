<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\Events\MediaKeysRewritten;
use WebxUi\Media\Images\ImageEditing;
use WebxUi\Media\Images\Optimizing\ImageOptimizer;
use WebxUi\Media\Images\Optimizing\LibraryOptimizing;
use WebxUi\Media\Models\MediaAlias;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Storage\FileUrls;

/**
 * «Convert to WebP»: a picture under a new key, and the whole site moved over to it.
 *
 * What matters is what would be quiet if it broke — a reference the rewrite missed is a broken
 * picture on a page nobody opened that day, a version restored next month brings back a key
 * whose bytes are gone, and an address a search engine kept answers 404.
 */
final class ConvertToWebpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAsAdmin();

        // Stand-ins for what modules keep: a page whose blocks are JSON and whose body is rich
        // text, a key–value table without an id, something holding the file by foreign key, a
        // table of versions, and a table the rewrite has no business in.
        Schema::create('test_pages', function (Blueprint $table): void {
            $table->id();
            $table->json('content')->nullable();
            $table->text('body')->nullable();
        });
        Schema::create('test_settings', function (Blueprint $table): void {
            $table->string('key');
            $table->text('value')->nullable();
        });
        Schema::create('test_covers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_file_id')->constrained('media_files');
        });
        Schema::create('entity_versions', function (Blueprint $table): void {
            $table->id();
            $table->longText('payload');
        });
    }

    #[Test]
    public function a_jpeg_becomes_a_webp_and_every_reference_follows_it(): void
    {
        $file = $this->oldPicture();
        $old = $file->path;
        $oldUrl = app(FileUrls::class)->url($file);
        $this->usedEverywhere($file);

        $this->postJson('/api/cms/media/files/optimize/pending', ['directory_id' => $file->directory_id, 'convert' => true])
            ->assertOk()
            ->assertJsonPath('data.ids', [$file->id]);

        $response = $this->postJson('/api/cms/media/files/optimize', ['ids' => [$file->id], 'convert' => true])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'converted')
            // The block JSON, the rich text, the setting and the version.
            ->assertJsonPath('data.0.references', 4);

        $file->refresh();
        $new = $file->path;

        // The same uuid in the same place, with the new extension — and a row that says so.
        $this->assertSame(substr($old, 0, -4).'.webp', $new);
        $this->assertSame(['webp', 'image/webp'], [$file->extension, $file->mime]);
        $this->assertStringEndsWith('.webp', $file->file_name);
        $this->assertSame((int) $response->json('data.0.after'), $file->size);
        $this->assertSame(md5((string) Storage::disk('public')->get($new)), $file->hash);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing($old);

        $page = DB::table('test_pages')->first();
        $this->assertNotNull($page);
        $this->assertStringNotContainsString(basename($old), (string) $page->content);
        $this->assertSame(['path' => $new, 'alt' => 'Sofa'], json_decode((string) $page->content, true)['blocks'][0]['image']);
        $this->assertStringContainsString(basename($new), (string) $page->body);
        $this->assertStringNotContainsString(basename($old), (string) $page->body);
        $this->assertSame($new, DB::table('test_settings')->where('key', 'logo')->value('value'));
        $this->assertStringContainsString(basename($new), (string) DB::table('entity_versions')->value('payload'));
        // The foreign key holds the row, which kept its id.
        $this->assertSame($file->id, (int) DB::table('test_covers')->value('media_file_id'));

        // The old key is remembered: the panel still finds the file by it, and its public
        // address sends whoever kept it to the new one.
        $this->assertSame($file->id, MediaAlias::query()->where('path', $old)->value('file_id'));
        $this->getJson('/api/cms/media/files/by-path?path='.urlencode($old))->assertOk()->assertJsonPath('data.path', $new);
        $this->get((string) parse_url($oldUrl, PHP_URL_PATH))
            ->assertStatus(301)
            ->assertRedirect(app(FileUrls::class)->url($file));

        // Converted is done: the next run has nothing left to do with it.
        $this->postJson('/api/cms/media/files/optimize/pending', ['directory_id' => $file->directory_id, 'convert' => true])
            ->assertJsonPath('data.ids', []);
    }

    #[Test]
    public function a_dry_run_says_what_would_happen_and_changes_nothing(): void
    {
        $file = $this->oldPicture();
        $this->usedEverywhere($file);
        $before = [$file->path, Storage::disk('public')->get($file->path), DB::table('test_pages')->value('content')];

        $result = app(LibraryOptimizing::class)->convert($file, dryRun: true);

        $this->assertSame('converted', $result['status']);
        $this->assertSame(4, $result['references']);
        $this->assertLessThan($result['before'], $result['after']);

        $this->artisan('webx:media:webp', ['--dry-run' => true])->assertSuccessful();

        $file->refresh();
        $this->assertSame($before, [$file->path, Storage::disk('public')->get($file->path), DB::table('test_pages')->value('content')]);
        $this->assertSame(0, MediaAlias::query()->count());
    }

    #[Test]
    public function the_command_converts_the_library(): void
    {
        $file = $this->oldPicture();
        $this->usedEverywhere($file);
        Event::fake([MediaKeysRewritten::class]);

        $this->artisan('webx:media:webp')->assertSuccessful();

        $this->assertSame('webp', $file->refresh()->extension);
        Event::assertDispatched(MediaKeysRewritten::class, fn (MediaKeysRewritten $event): bool => array_values($event->paths) === [$file->path]);
    }

    #[Test]
    public function a_transparent_png_stays_transparent(): void
    {
        config(['webx-media.optimize.enabled' => false]);
        $file = $this->store($this->transparentPng(), 'logo.png');
        config(['webx-media.optimize.enabled' => true]);

        $this->assertSame('converted', app(LibraryOptimizing::class)->convert($file)['status']);

        $image = imagecreatefromstring((string) Storage::disk('public')->get($file->refresh()->path));
        $this->assertNotFalse($image);
        // The corner was left empty in the PNG; 127 is fully transparent in GD.
        $this->assertSame(127, (imagecolorat($image, 2, 2) >> 24) & 0x7F);
    }

    #[Test]
    public function what_is_not_a_still_jpeg_or_png_is_left_alone(): void
    {
        $gif = $this->store(UploadedFile::fake()->image('loop.gif', 40, 40), 'loop.gif');
        $webp = $this->store($this->photograph(800, 600), 'new.jpg');
        $svg = $this->store(UploadedFile::fake()->createWithContent('mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'), 'mark.svg');

        $this->assertSame('webp', $webp->extension);

        foreach ([$gif, $webp, $svg] as $file) {
            $this->assertSame('skipped', app(LibraryOptimizing::class)->convert($file)['status'], $file->extension);
        }

        $this->assertSame([], app(LibraryOptimizing::class)->convertible()->pluck('id')->all());
    }

    #[Test]
    public function a_heic_becomes_a_webp_where_imagick_reads_it(): void
    {
        if (! app(ImageOptimizer::class)->readsHeic()) {
            $this->markTestSkipped('Imagick with a HEIC decoder (libheif) is not installed here.');
        }

        $image = new \Imagick;
        $image->newImage(800, 600, new \ImagickPixel('orange'));
        $image->addNoiseImage(\Imagick::NOISE_GAUSSIAN);
        $image->setImageFormat('heic');
        $path = tempnam(sys_get_temp_dir(), 'wx').'.heic';
        file_put_contents($path, $image->getImageBlob());

        config(['webx-media.optimize.enabled' => false]);
        $file = $this->store(new UploadedFile($path, 'IMG_0001.heic', 'image/heic', null, true), 'IMG_0001.heic');
        config(['webx-media.optimize.enabled' => true]);

        $this->assertSame('converted', app(LibraryOptimizing::class)->convert($file)['status']);
        $this->assertSame('webp', $file->refresh()->extension);
    }

    #[Test]
    public function the_original_kept_by_the_editor_comes_back_in_the_new_format(): void
    {
        $file = $this->oldPicture();
        app(ImageEditing::class)->apply($file, ['rotate' => 90]);
        $original = (string) $file->refresh()->original_path;

        app(LibraryOptimizing::class)->convert($file);
        $file->refresh();

        // Kept as it arrived: the editor's copy is the picture before anything was done to it.
        $this->assertSame($original, $file->original_path);
        Storage::disk('public')->assertExists($original);

        app(ImageEditing::class)->restore($file);

        $restored = getimagesizefromstring((string) Storage::disk('public')->get($file->refresh()->path));
        $this->assertNotFalse($restored);
        $this->assertSame('image/webp', $restored['mime']);
        $this->assertSame([1600, 1000], [$restored[0], $restored[1]]);
    }

    #[Test]
    public function the_old_previews_go_with_the_old_key(): void
    {
        $file = $this->oldPicture();
        $this->get("/api/cms/media/files/{$file->id}/thumb?w=320&h=320&fit=cover")->assertRedirect();
        $thumbs = 'media/thumbs/'.pathinfo($file->path, PATHINFO_FILENAME);
        $this->assertNotEmpty(Storage::disk('public')->allFiles($thumbs));

        app(LibraryOptimizing::class)->convert($file);

        $this->assertSame([], Storage::disk('public')->allFiles($thumbs));
    }

    #[Test]
    public function an_address_nobody_had_is_still_a_404(): void
    {
        $this->get('/storage/media/aa/bb/00000000-0000-0000-0000-000000000000.jpg')->assertNotFound();
    }

    /** The file named the ways a site names one, plus places a rewrite must not touch. */
    private function usedEverywhere(MediaFile $file): void
    {
        $url = app(FileUrls::class)->url($file);

        DB::table('test_pages')->insert([
            // `json_encode` escapes the slashes; the basename has none, so it is found anyway.
            'content' => json_encode(['blocks' => [['type' => 'hero', 'image' => ['path' => $file->path, 'alt' => 'Sofa']]]]),
            'body' => '<p>Look: <img src="'.$url.'" alt=""></p>',
        ]);
        DB::table('test_settings')->insert(['key' => 'logo', 'value' => $file->path]);
        DB::table('test_covers')->insert(['media_file_id' => $file->id]);
        DB::table('entity_versions')->insert(['payload' => json_encode(['cover' => $file->path])]);
    }

    /** A JPEG uploaded before there was a pipeline, the kind a site has from its first months. */
    private function oldPicture(): MediaFile
    {
        config(['webx-media.optimize.enabled' => false]);
        $file = $this->store($this->photograph(1600, 1000), 'old.jpg');
        config(['webx-media.optimize.enabled' => true]);

        $this->assertSame('jpg', $file->extension);

        return $file;
    }

    private function store(UploadedFile $upload, string $name): MediaFile
    {
        $directory = MediaDirectory::query()->firstOrCreate(['parent_id' => null, 'title' => 'Library']);

        return app(FileStore::class)->store($upload, $directory);
    }

    private function photograph(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);

        for ($i = 0; $i < 4000; $i++) {
            $colour = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), (int) $colour);
        }

        $path = tempnam(sys_get_temp_dir(), 'wx').'.jpg';
        imagejpeg($image, $path, 100);

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    }

    private function transparentPng(): UploadedFile
    {
        $image = imagecreatetruecolor(600, 400);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));

        // Noise in the middle, pixel by pixel, so that a PNG is the expensive way to keep it; the
        // corners stay empty.
        for ($x = 50; $x < 550; $x++) {
            for ($y = 50; $y < 350; $y++) {
                imagesetpixel($image, $x, $y, (int) imagecolorallocatealpha($image, random_int(0, 255), random_int(0, 255), random_int(0, 255), 0));
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'wx').'.png';
        imagepng($image, $path);

        return new UploadedFile($path, 'logo.png', 'image/png', null, true);
    }
}
