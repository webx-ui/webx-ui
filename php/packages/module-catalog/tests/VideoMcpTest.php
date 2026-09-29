<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Gallery\FetchVideo;
use WebxUi\Catalog\Gallery\Gallery;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The gallery by an agent's doors (§8 of the video spec): six tools behind the gallery's own
 * permission and the module's scopes, `dry_run` that fetches nothing and writes nothing, and the
 * flag that refuses a video to the agent as it does to the panel.
 */
final class VideoMcpTest extends TestCase
{
    private const TOOLS = [
        'catalog_products_gallery' => 'read',
        'catalog_products_gallery_add' => 'write',
        'catalog_products_gallery_update' => 'write',
        'catalog_products_gallery_order' => 'write',
        'catalog_products_gallery_video' => 'write',
        'catalog_products_gallery_remove' => 'write',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function the_six_tools_are_behind_the_gallerys_permission_and_the_modules_scopes(): void
    {
        $tools = [];

        foreach ($this->app->make(ToolRegistry::class)->toolsOf('catalog') as $tool) {
            $tools[$tool->fullName()] = $tool;
        }

        foreach (self::TOOLS as $name => $access) {
            $this->assertArrayHasKey($name, $tools);
            $this->assertSame('catalog:'.$access, $tools[$name]->scope());
            $this->assertSame($access === 'read' ? ['catalog.view', 'catalog.manage'] : ['catalog.manage'], $tools[$name]->permissions());
        }

        // A reader reads the gallery and is refused every change to it.
        $product = $this->product('Belt');
        $image = $this->picture($product);
        $reader = $this->editor(['catalog.view']);

        $this->agent('catalog_products_gallery', ['product' => $product->id], $reader)->assertOk();
        $this->agent('catalog_products_gallery_remove', ['product' => $product->id, 'image' => $image->id], $reader)->assertHasErrors(['[catalog.manage]']);
        $this->assertModelExists($image);
    }

    #[Test]
    public function the_gallery_is_read_in_order_with_its_videos(): void
    {
        $product = $this->product('Belt');
        $first = $this->picture($product);
        $first->forceFill(['video_provider' => 'youtube', 'video' => 'aqz-KE-bpKQ'])->save();
        $this->picture($product, 20);

        $gallery = $this->content($this->agent('catalog_products_gallery', ['product' => $product->id]));

        $this->assertCount(2, $gallery['images']);
        $this->assertSame($first->id, $gallery['images'][0]['id']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ', $gallery['images'][0]['video']['embed']);
        $this->assertNull($gallery['images'][1]['video']);
    }

    #[Test]
    public function an_address_is_added_as_a_picture_a_video_or_a_download(): void
    {
        Http::fake([
            'i.ytimg.com/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
            'www.youtube.com/oembed*' => Http::response(['title' => 'From oEmbed'], 200),
        ]);
        Queue::fake();
        $product = $this->product('Belt');
        $this->picture($product);

        // dry_run says what the address is and fetches nothing.
        $dry = $this->content($this->agent('catalog_products_gallery_add', ['product' => $product->id, 'url' => 'https://youtu.be/aqz-KE-bpKQ', 'dry_run' => true]));
        $this->assertTrue($dry['dry_run']);
        $this->assertStringContainsString('youtube video aqz-KE-bpKQ', $dry['would']);
        Http::assertNothingSent();
        $this->assertSame(1, ProductImage::query()->count());

        // The caption given wins over oEmbed's, and position 0 makes it the main picture.
        $added = $this->content($this->agent('catalog_products_gallery_add', [
            'product' => $product->id,
            'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
            'alt' => ['en' => 'The belt, worn'],
            'position' => 0,
        ]));
        $this->assertSame('youtube', $added['image']['video']['provider']);
        $this->assertSame('The belt, worn', $added['image']['alt']['en']);
        $this->assertSame($added['image']['id'], $this->mainOf($product));
        $this->assertSame(1, HistoryEntry::query()->where('subject_type', 'catalog.product')->where('source', 'mcp')->count());

        $queued = $this->content($this->agent('catalog_products_gallery_add', ['product' => $product->id, 'url' => 'https://supplier.test/belt.webm']));
        $this->assertTrue($queued['queued']);
        Queue::assertPushed(FetchVideo::class, 1);
    }

    #[Test]
    public function captions_and_order_are_written_and_a_dry_run_takes_them_back(): void
    {
        $product = $this->product('Belt');
        [$one, $two] = [$this->picture($product), $this->picture($product, 20)];

        $this->agent('catalog_products_gallery_update', ['product' => $product->id, 'image' => $one->id, 'alt' => 'Left', 'dry_run' => true])->assertOk();
        $this->assertSame([], $one->refresh()->getTranslations('alt'));

        $this->agent('catalog_products_gallery_update', ['product' => $product->id, 'image' => $one->id, 'alt' => 'Left', 'title' => ['en' => 'Side']])->assertOk();
        $this->assertSame('Left', $one->refresh()->getTranslation('alt', 'en'));
        $this->assertSame('Side', $one->getTranslation('title', 'en'));

        $this->agent('catalog_products_gallery_order', ['product' => $product->id, 'images' => [$two->id]])->assertHasErrors(['every picture']);
        $this->agent('catalog_products_gallery_order', ['product' => $product->id, 'images' => [$two->id, $one->id], 'dry_run' => true])->assertOk();
        $this->assertSame($one->id, $this->mainOf($product));

        $this->agent('catalog_products_gallery_order', ['product' => $product->id, 'images' => [$two->id, $one->id]])->assertOk();
        $this->assertSame($two->id, $this->mainOf($product));
    }

    #[Test]
    public function a_video_is_attached_and_taken_off_and_a_dry_run_changes_nothing(): void
    {
        $product = $this->product('Belt');
        $image = $this->picture($product);
        $arguments = ['product' => $product->id, 'image' => $image->id];

        $this->agent('catalog_products_gallery_video', [...$arguments, 'url' => 'https://youtu.be/aqz-KE-bpKQ', 'dry_run' => true])->assertOk();
        $this->assertNull($image->refresh()->video);

        $attached = $this->content($this->agent('catalog_products_gallery_video', [...$arguments, 'url' => 'https://youtu.be/aqz-KE-bpKQ']));
        $this->assertSame('https://www.youtube.com/watch?v=aqz-KE-bpKQ', $attached['image']['video']['url']);

        // A page that is neither a provider's video nor a file is refused.
        Http::fake(['https://supplier.test/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
        $this->agent('catalog_products_gallery_video', [...$arguments, 'url' => 'https://supplier.test/page'])->assertHasErrors(['not a video']);

        Queue::fake();
        $this->content($this->agent('catalog_products_gallery_video', [...$arguments, 'url' => 'https://supplier.test/clip.mp4']));
        Queue::assertPushed(FetchVideo::class, static fn (FetchVideo $job): bool => $job->image === $image->id);

        $this->agent('catalog_products_gallery_video', [...$arguments, 'url' => null, 'dry_run' => true])->assertOk();
        $this->assertSame('aqz-KE-bpKQ', $image->refresh()->video);

        $detached = $this->content($this->agent('catalog_products_gallery_video', [...$arguments, 'url' => null]));
        $this->assertNull($detached['image']['video']);
    }

    #[Test]
    public function a_picture_is_removed_with_its_files_and_a_dry_run_keeps_it(): void
    {
        $product = $this->product('Belt');
        $image = $this->picture($product);

        $dry = $this->content($this->agent('catalog_products_gallery_remove', ['product' => $product->id, 'image' => $image->id, 'dry_run' => true]));
        $this->assertSame($image->id, $dry['would_remove']['id']);
        $this->assertModelExists($image);

        $this->agent('catalog_products_gallery_remove', ['product' => $product->id, 'image' => $image->id])->assertOk();
        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($image->path);

        // Somebody else's picture is not this product's to remove.
        $other = $this->picture($this->product('Boot'));
        $this->agent('catalog_products_gallery_remove', ['product' => $product->id, 'image' => $other->id])->assertHasErrors(['no such picture']);
    }

    #[Test]
    public function the_fields_resource_tells_the_flag_and_the_limit(): void
    {
        $fields = ($this->resource('catalog://fields')->handler)();

        $this->assertTrue($fields['video']);
        $this->assertSame(2048, $fields['video_max_size_mb']);
        $this->assertSame(['video/mp4', 'video/webm'], $fields['video_types']);
    }

    #[Test]
    #[DefineEnvironment('withoutVideo')]
    public function switched_off_a_video_is_refused_to_the_agent_too(): void
    {
        $product = $this->product('Belt');
        $image = $this->picture($product);

        $this->assertFalse(($this->resource('catalog://fields')->handler)()['video']);

        $this->agent('catalog_products_gallery_video', ['product' => $product->id, 'image' => $image->id, 'url' => 'https://youtu.be/aqz-KE-bpKQ'])
            ->assertHasErrors(['switched off']);
        $this->agent('catalog_products_gallery_video', ['product' => $product->id, 'image' => $image->id, 'url' => 'https://youtu.be/aqz-KE-bpKQ', 'dry_run' => true])
            ->assertHasErrors(['switched off']);
        $this->agent('catalog_products_gallery_add', ['product' => $product->id, 'url' => 'https://supplier.test/clip.mp4'])
            ->assertHasErrors(['switched off']);

        $this->assertNull($image->refresh()->video);
    }

    /**
     * @param  Application  $app
     */
    protected function withoutVideo($app): void
    {
        $app['config']->set('webx-catalog.fields.video', false);
    }

    private function mainOf(Product $product): ?int
    {
        return Product::query()->findOrFail($product->id)->mainImage()?->id;
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("There is no resource [{$uri}].");
    }

    private function picture(Product $product, int $width = 10): ProductImage
    {
        return $this->app->make(Gallery::class)->upload($product, UploadedFile::fake()->image("p{$width}.jpg", $width, 10));
    }

    private function jpeg(): string
    {
        $file = UploadedFile::fake()->image('cover.jpg', 480, 360);

        return (string) file_get_contents($file->getRealPath());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertOk()->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
