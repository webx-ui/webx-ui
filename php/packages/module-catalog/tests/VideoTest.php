<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Uploads\FreeSpace;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Admin\Uploads\Uploads;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Gallery\FetchVideo;
use WebxUi\Catalog\Gallery\Gallery;
use WebxUi\Catalog\Gallery\Video\VideoProvider;
use WebxUi\Catalog\Gallery\Video\VideoProviders;
use WebxUi\Catalog\Gallery\Video\YouTubeProvider;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Widgets\Consent;
use WebxUi\Widgets\Video\VideoProviders as PlayedProviders;
use WebxUi\Widgets\Video\YouTube as PlayedYouTube;

/**
 * Videos in the gallery (the video spec, §11): the providers' links, files through the chunked
 * upload with their content checked, a direct link on the queue, the files going with what holds
 * them, the journal, the storefront and its markup, and the flag that switches all of it off.
 */
final class VideoTest extends TestCase
{
    /** An `ftyp` box: what finfo reads as an MP4 file. */
    private const MP4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";

    private string $uploads;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // The pieces of an upload in a folder of the test's own, never short of room.
        $this->uploads = sys_get_temp_dir().DIRECTORY_SEPARATOR.'webx-catalog-uploads-'.bin2hex(random_bytes(4));
        $this->app->instance(FreeSpace::class, new class extends FreeSpace
        {
            public function bytes(string $path): int
            {
                return PHP_INT_MAX;
            }
        });
        $this->app->singleton(Uploads::class, fn ($app): Uploads => new Uploads(
            $app->make('config'),
            $app->make(UploadPurposes::class),
            $app->make(FreeSpace::class),
            $this->uploads,
        ));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->uploads);

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function links(): iterable
    {
        yield 'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'];
        yield 'watch with more' => ['https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42', 'dQw4w9WgXcQ'];
        yield 'short link' => ['https://youtu.be/dQw4w9WgXcQ?si=abc', 'dQw4w9WgXcQ'];
        yield 'shorts' => ['https://www.youtube.com/shorts/aqz-KE-bpKQ', 'aqz-KE-bpKQ'];
        yield 'embed' => ['https://www.youtube.com/embed/aqz-KE-bpKQ?start=5', 'aqz-KE-bpKQ'];
        yield 'nocookie embed' => ['https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ', 'aqz-KE-bpKQ'];
        yield 'live' => ['https://www.youtube.com/live/aqz-KE-bpKQ', 'aqz-KE-bpKQ'];
        yield 'mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'];
        yield 'music' => ['https://music.youtube.com/watch?v=dQw4w9WgXcQ&list=RD', 'dQw4w9WgXcQ'];
        yield 'no scheme' => ['youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'];
        yield 'plain http' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'];

        yield 'id too short' => ['https://www.youtube.com/watch?v=dQw4w9WgXc', null];
        yield 'id too long' => ['https://youtu.be/dQw4w9WgXcQQ', null];
        yield 'a channel' => ['https://www.youtube.com/@blender', null];
        yield 'a playlist' => ['https://www.youtube.com/playlist?list=PL0123456789', null];
        yield 'another host' => ['https://www.youtube.com.evil.test/watch?v=dQw4w9WgXcQ', null];
        yield 'a lookalike' => ['https://notyoutube.com/watch?v=dQw4w9WgXcQ', null];
        yield 'vimeo' => ['https://vimeo.com/76979871', null];
        yield 'another scheme' => ['ftp://youtu.be/dQw4w9WgXcQ', null];
        yield 'rubbish' => ['not a link at all', null];
    }

    #[Test]
    #[DataProvider('links')]
    public function a_youtube_link_is_read_in_every_form_it_is_shared_in(string $url, ?string $id): void
    {
        $this->assertSame($id, (new YouTubeProvider)->idFrom($url));
    }

    #[Test]
    public function a_satellite_registers_a_provider_of_its_own(): void
    {
        $providers = $this->app->make(VideoProviders::class);
        $providers->register(new class implements VideoProvider
        {
            public function key(): string
            {
                return 'clips';
            }

            public function label(): string
            {
                return 'Clips';
            }

            public function idFrom(string $url): ?string
            {
                return preg_match('#^https://clips\.test/(\d+)$#', $url, $match) === 1 ? $match[1] : null;
            }

            public function embedUrl(string $id): string
            {
                return 'https://clips.test/embed/'.$id;
            }

            public function watchUrl(string $id): string
            {
                return 'https://clips.test/'.$id;
            }

            public function posterUrls(string $id): array
            {
                return ['https://clips.test/'.$id.'.jpg'];
            }

            public function title(string $id): ?string
            {
                return null;
            }
        });

        $image = $this->picture($this->product('Belt'));
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->video($image), ['url' => 'https://clips.test/42'])
            ->assertOk()
            ->assertJsonPath('data.video.provider', 'clips')
            ->assertJsonPath('data.video.embed', 'https://clips.test/embed/42');

        // The storefront plays it through the widgets, which hear of it when first asked; YouTube
        // they know themselves.
        $played = $this->app->make(PlayedProviders::class);
        $clip = $played->find('https://clips.test/42');
        $this->assertNotNull($clip);
        $this->assertSame('https://clips.test/embed/42?autoplay=1', $clip->provider->embed($clip));
        $this->assertSame('https://clips.test/42', $clip->provider->page($clip));
        $this->assertInstanceOf(PlayedYouTube::class, $played->find('https://youtu.be/aqz-KE-bpKQ')?->provider);

        $this->expectException(LogicException::class);
        $file = $this->createStub(VideoProvider::class);
        $file->method('key')->willReturn('file');
        $providers->register($file);
    }

    #[Test]
    public function a_youtube_link_adds_its_cover_with_the_video_on_it(): void
    {
        Http::fake([
            'i.ytimg.com/vi/aqz-KE-bpKQ/maxresdefault.jpg' => Http::response('', 404),
            'i.ytimg.com/vi/aqz-KE-bpKQ/hqdefault.jpg' => Http::response($this->jpeg(480, 360), 200, ['Content-Type' => 'image/jpeg']),
            'www.youtube.com/oembed*' => Http::response(['title' => 'Big Buck Bunny'], 200),
        ]);
        $product = $this->product('Belt');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api("products/{$product->id}/images"), [
            'url' => 'https://youtu.be/aqz-KE-bpKQ',
        ])->assertCreated()
            ->assertJsonPath('data.width', 480)
            ->assertJsonPath('data.alt.en', 'Big Buck Bunny')
            ->assertJsonPath('data.video', [
                'provider' => 'youtube',
                'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
                'embed' => 'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ',
                'duration' => null,
            ]);

        Storage::disk('public')->assertExists((string) $response->json('data.path'));
        Http::assertSent(static fn ($request): bool => str_contains($request->url(), 'maxresdefault'));

        // The journal names the picture and what plays over it.
        $entry = HistoryEntry::query()->where('subject_type', 'catalog.product')->latest('id')->firstOrFail();
        $this->assertSame('images', $entry->changes[0]['field']);
        $this->assertStringEndsWith('▶ YouTube', (string) $entry->changes[0]['to']);
    }

    #[Test]
    public function a_direct_link_to_a_file_is_downloaded_on_the_queue(): void
    {
        Queue::fake();
        $product = $this->product('Belt');

        $this->actingAs($this->editor(), 'cms')->postJson($this->api("products/{$product->id}/images"), [
            'url' => 'https://supplier.test/clips/belt.mp4',
        ])->assertStatus(202)->assertJsonPath('data.queued', true);

        $this->assertSame(0, ProductImage::query()->count());
        Queue::assertPushed(FetchVideo::class, static fn (FetchVideo $job): bool => $job->product === $product->id && $job->image === null);

        // One that says what it is only by its header is recognised by the header.
        Http::fake(['https://supplier.test/*' => Http::response(self::MP4, 200, ['Content-Type' => 'video/mp4'])]);
        $this->actingAs($this->editor(), 'cms')->postJson($this->api("products/{$product->id}/images"), [
            'url' => 'https://supplier.test/stream?id=7',
        ])->assertStatus(202);
        Queue::assertPushed(FetchVideo::class, 2);

        // The job: the file lands on the disk and the row appears, behind a plain poster.
        (new FetchVideo($product->id, 'https://supplier.test/clips/belt.mp4'))->handle($this->app->make(Gallery::class));

        $image = ProductImage::query()->sole();
        $this->assertSame(VideoProviders::FILE, $image->video_provider);
        $this->assertMatchesRegularExpression("#^catalog/0/{$product->id}/".sha1(self::MP4).'\\.mp4$#', (string) $image->video);
        Storage::disk('public')->assertExists((string) $image->video);
        Storage::disk('public')->assertExists($image->path);
    }

    #[Test]
    public function the_queue_refuses_a_file_over_the_limit_and_what_is_not_a_video(): void
    {
        config()->set('webx-catalog.videos.max_size_mb', 1);
        $product = $this->product('Belt');
        $picture = $this->picture($product);
        $gallery = $this->app->make(Gallery::class);

        Http::fake([
            'https://supplier.test/huge.mp4' => Http::response(self::MP4.str_repeat("\0", 1048577), 200, ['Content-Type' => 'video/mp4']),
            'https://supplier.test/fake.mp4' => Http::response($this->jpeg(10, 10), 200, ['Content-Type' => 'video/mp4']),
        ]);

        foreach (['huge', 'fake'] as $name) {
            try {
                $gallery->download($product->id, "https://supplier.test/{$name}.mp4");
                $this->fail("The {$name} file was taken.");
            } catch (RuntimeException) {
            }
        }

        // Neither left a row, a poster or a file behind; the picture that was there is untouched.
        $this->assertSame([$picture->id], ProductImage::query()->pluck('id')->all());
        $this->assertCount(1, Storage::disk('public')->allFiles("catalog/0/{$product->id}"));
    }

    #[Test]
    public function an_uploaded_file_is_checked_by_its_content_and_stored_under_its_hash(): void
    {
        $editor = $this->editor();
        $product = $this->product('Belt');
        $image = $this->picture($product);

        // A picture renamed .mp4 is refused, and its pieces go.
        $fake = $this->uploaded($editor, $this->jpeg(10, 10), 'clip.mp4');
        $this->actingAs($editor, 'cms')->postJson($this->video($image), ['upload' => $fake])
            ->assertStatus(422)
            ->assertJsonValidationErrors('upload');
        $this->assertSame([], glob($this->uploads.'/*.part') ?: []);

        $id = $this->uploaded($editor, self::MP4, 'clip.mp4');
        $response = $this->actingAs($editor, 'cms')->postJson($this->video($image), ['upload' => $id, 'duration' => 65])
            ->assertOk()
            ->assertJsonPath('data.video.provider', 'file')
            ->assertJsonPath('data.video.embed', null)
            ->assertJsonPath('data.video.duration', 65);

        $path = sprintf('catalog/0/%d/%s.mp4', $product->id, sha1(self::MP4));
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith($path, (string) $response->json('data.video.url'));

        // Somebody else's upload is nobody's: the same answer as a missing one.
        $theirs = $this->uploaded($this->editor(), self::MP4.'x', 'theirs.mp4');
        $this->actingAs($editor, 'cms')->postJson($this->video($image), ['upload' => $theirs])->assertNotFound();
    }

    #[Test]
    public function an_upload_over_the_limit_is_refused_before_it_starts(): void
    {
        config()->set('webx-catalog.videos.max_size_mb', 1);

        $this->actingAs($this->editor(), 'cms')->postJson('/api/cms/uploads', [
            'name' => 'huge.mp4',
            'size' => 1048577,
            'type' => 'video/mp4',
            'fingerprint' => 'huge.mp4|1048577|1',
            'purpose' => 'catalog.video',
        ])->assertStatus(422)->assertJsonValidationErrors('size');

        // Behind `catalog.manage`, like the rest of the gallery.
        $this->actingAs($this->editor(['catalog.view']), 'cms')->postJson('/api/cms/uploads', [
            'name' => 'clip.mp4',
            'size' => 100,
            'type' => 'video/mp4',
            'fingerprint' => 'clip.mp4|100|1',
            'purpose' => 'catalog.video',
        ])->assertForbidden();
    }

    #[Test]
    public function replacing_and_taking_off_a_video_delete_its_file_and_the_journal_hears_both(): void
    {
        $editor = $this->editor();
        $product = $this->product('Belt');
        $image = $this->picture($product);
        $first = sprintf('catalog/0/%d/%s.mp4', $product->id, sha1(self::MP4));

        $this->actingAs($editor, 'cms')->postJson($this->video($image), ['upload' => $this->uploaded($editor, self::MP4, 'clip.mp4')])->assertOk();
        Storage::disk('public')->assertExists($first);

        // A YouTube link in its place takes the file away.
        $this->actingAs($editor, 'cms')->postJson($this->video($image), ['url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ'])
            ->assertOk()
            ->assertJsonPath('data.video.provider', 'youtube');
        Storage::disk('public')->assertMissing($first);

        $second = sprintf('catalog/0/%d/%s.mp4', $product->id, sha1(self::MP4.'2'));
        $this->actingAs($editor, 'cms')->postJson($this->video($image), ['upload' => $this->uploaded($editor, self::MP4.'2', 'other.mp4')])->assertOk();
        Storage::disk('public')->assertExists($second);

        $this->actingAs($editor, 'cms')->deleteJson($this->video($image))->assertOk()->assertJsonPath('data.video', null);
        Storage::disk('public')->assertMissing($second);
        Storage::disk('public')->assertExists($image->path);

        $changes = HistoryEntry::query()
            ->where('subject_type', 'catalog.product')
            ->where('subject_id', $product->id)
            ->where('event', HistoryEntry::UPDATED)
            ->orderBy('id')
            ->get()
            ->map(static fn (HistoryEntry $entry): array => $entry->changes[0])
            ->all();

        $picture = basename($image->path);
        $this->assertSame([
            ['field' => 'images', 'from' => null, 'to' => "«{$picture}» ▶ clip.mp4"],
            ['field' => 'images', 'from' => "«{$picture}» ▶ ".basename($first), 'to' => "«{$picture}» ▶ YouTube"],
            ['field' => 'images', 'from' => "«{$picture}» ▶ YouTube", 'to' => "«{$picture}» ▶ other.mp4"],
            ['field' => 'images', 'from' => "«{$picture}» ▶ ".basename($second), 'to' => null],
        ], array_map(static fn (array $change): array => array_intersect_key($change, array_flip(['field', 'from', 'to'])), $changes));
    }

    #[Test]
    public function deleting_a_picture_deletes_its_video_and_a_deleted_product_keeps_both(): void
    {
        $editor = $this->editor();
        $product = $this->product('Belt', $this->category('belts'));
        $kept = $this->picture($product);
        $gone = $this->picture($product, 20);

        $this->actingAs($editor, 'cms')->postJson($this->video($kept), ['upload' => $this->uploaded($editor, self::MP4, 'kept.mp4')])->assertOk();
        $this->actingAs($editor, 'cms')->postJson($this->video($gone), ['upload' => $this->uploaded($editor, self::MP4.'gone', 'gone.mp4')])->assertOk();
        $keptVideo = (string) $kept->refresh()->video;
        $goneVideo = (string) $gone->refresh()->video;

        $this->actingAs($editor, 'cms')->deleteJson($this->api("products/{$product->id}/images/{$gone->id}"))->assertNoContent();
        Storage::disk('public')->assertMissing($goneVideo);
        Storage::disk('public')->assertExists($keptVideo);

        $this->actingAs($editor, 'cms')->deleteJson($this->api("products/{$product->id}"))->assertNoContent();
        Storage::disk('public')->assertExists($keptVideo);
        Storage::disk('public')->assertExists($kept->path);
    }

    #[Test]
    public function the_same_file_on_two_pictures_is_one_file_and_stays_while_either_holds_it(): void
    {
        $editor = $this->editor();
        $product = $this->product('Belt');
        [$one, $two] = [$this->picture($product), $this->picture($product, 20)];

        $this->actingAs($editor, 'cms')->postJson($this->video($one), ['upload' => $this->uploaded($editor, self::MP4, 'a.mp4')])->assertOk();
        $this->actingAs($editor, 'cms')->postJson($this->video($two), ['upload' => $this->uploaded($editor, self::MP4, 'b.mp4', 'again')])->assertOk();

        $this->actingAs($editor, 'cms')->deleteJson($this->video($one))->assertOk();
        Storage::disk('public')->assertExists((string) $two->refresh()->video);
    }

    #[Test]
    public function the_storefront_plays_through_the_video_of_the_widgets_behind_the_consent(): void
    {
        $product = $this->product('Belt', $this->category('belts'), ['summary' => 'A belt.']);
        $file = $this->picture($product);
        $file->forceFill(['video_provider' => 'file', 'video' => "catalog/0/{$product->id}/clip.mp4", 'video_duration' => 65])->save();
        $tube = $this->picture($product, 20);
        $tube->forceFill(['video_provider' => 'youtube', 'video' => 'aqz-KE-bpKQ'])->save();

        $page = $this->get("/belt-{$product->id}")->assertOk();

        // The picture is the poster, in its own shape; YouTube waits for consent to media, and
        // nothing of it is loaded before — not even its preview.
        $page->assertSee('webx-video webx-video--youtube is-blocked', false)
            ->assertSee('--webx-video-ratio: 20 / 10', false)
            ->assertSee('href="https://www.youtube.com/watch?v=aqz-KE-bpKQ"', false)
            ->assertSee('youtube-nocookie.com/embed/aqz-KE-bpKQ?autoplay=1', false)
            ->assertSee('class="webx-video__poster" src="'.$tube->url().'"', false)
            ->assertSee('data-webx-video-always', false)
            ->assertDontSee('<iframe', false)
            ->assertDontSee('ytimg', false)
            ->assertDontSee('webx-catalog-product__play', false)
            ->assertDontSee('data-webx-embed', false);

        // A file of the site is first party: a <video> asking nobody's consent.
        $page->assertSee('webx-video webx-video--file', false)
            ->assertSee('preload="none"', false)
            ->assertSee('poster="'.$file->url().'"', false)
            ->assertSee('src="/storage/catalog/0/'.$product->id.'/clip.mp4"', false);

        // With consent to media the placeholder is gone; the facade still waits for the click.
        $this->withUnencryptedCookie(Consent::COOKIE, json_encode(['v' => 1, 'd' => '2026-10-09', 'c' => ['media']], JSON_THROW_ON_ERROR))
            ->get("/belt-{$product->id}")
            ->assertOk()
            ->assertSee('webx-video webx-video--youtube"', false)
            ->assertDontSee('is-blocked', false)
            ->assertDontSee('<iframe', false);

        $page->assertSee('"subjectOf":[{"@type":"VideoObject"', false)
            ->assertSee('"contentUrl":"/storage/catalog/0/'.$product->id.'/clip.mp4"', false)
            ->assertSee('"duration":"PT1M5S"', false)
            ->assertSee('"embedUrl":"https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ"', false)
            ->assertSee('"description":"A belt."', false);
    }

    #[Test]
    #[DefineEnvironment('withoutVideo')]
    public function switched_off_there_is_no_player_no_markup_and_no_new_video(): void
    {
        $editor = $this->editor();
        $product = $this->product('Belt', $this->category('belts'));
        $image = $this->picture($product);
        $image->forceFill(['video_provider' => 'youtube', 'video' => 'aqz-KE-bpKQ'])->save();

        $this->get("/belt-{$product->id}")
            ->assertOk()
            ->assertDontSee('webx-catalog-product__play', false)
            ->assertDontSee('VideoObject', false);

        $this->actingAs($editor, 'cms')->postJson($this->video($image), ['url' => 'https://youtu.be/aqz-KE-bpKQ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
        $this->actingAs($editor, 'cms')->postJson($this->video($image), ['upload' => $this->uploaded($editor, self::MP4, 'clip.mp4')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('upload');
        $this->actingAs($editor, 'cms')->postJson($this->api("products/{$product->id}/images"), ['url' => 'https://youtu.be/aqz-KE-bpKQ'])
            ->assertStatus(422);

        // What was attached is kept, and the panel's form hears the flag.
        $this->assertSame('aqz-KE-bpKQ', $image->refresh()->video);
        $this->assertFalse($this->galleryProps()['video']);
    }

    #[Test]
    public function the_form_hears_the_flag_and_the_limits(): void
    {
        $props = $this->galleryProps();

        $this->assertTrue($props['video']);
        $this->assertSame(['video/mp4', 'video/webm'], $props['videoTypes']);
        $this->assertSame(2048 * 1048576, $props['videoMaxBytes']);
    }

    /**
     * @param  Application  $app
     */
    protected function withoutVideo($app): void
    {
        $app['config']->set('webx-catalog.fields.video', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function galleryProps(): array
    {
        $find = static function (array $nodes) use (&$find): ?array {
            foreach ($nodes as $node) {
                if (($node['id'] ?? null) === 'gallery') {
                    return $node;
                }

                $found = $find($node['children'] ?? []);

                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        };

        $tree = $this->app->make(ScreenRegistry::class)->tree(Product::SCREEN);
        $node = $find(isset($tree['id']) ? [$tree] : ($tree['children'] ?? $tree));

        return (array) ($node['props'] ?? []);
    }

    private function picture(Product $product, int $width = 10): ProductImage
    {
        return $this->app->make(Gallery::class)->upload($product, UploadedFile::fake()->image("p{$width}.jpg", $width, 10));
    }

    private function video(ProductImage $image): string
    {
        return $this->api("products/{$image->product_id}/images/{$image->id}/video");
    }

    /** A finished chunked upload of this content, as the panel leaves it. */
    private function uploaded(CmsUser $admin, string $contents, string $name, string $modified = '1'): string
    {
        $uploads = $this->app->make(Uploads::class);
        [$upload] = $uploads->start($admin, Gallery::UPLOAD_PURPOSE, $name, strlen($contents), 'video/mp4', $name.'|'.strlen($contents).'|'.$modified);

        $body = fopen('php://memory', 'r+b');
        fwrite($body, $contents);
        rewind($body);
        $uploads->append($upload, 0, $body, strlen($contents));
        fclose($body);

        return $upload->id;
    }

    private function jpeg(int $width, int $height): string
    {
        $file = UploadedFile::fake()->image('cover.jpg', $width, $height);

        return (string) file_get_contents($file->getRealPath());
    }
}
