<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\AssetDownloads;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Menu\Models\MenuItem;

/**
 * An item that is a file of the library — the price list in the footer (webx-ui#210).
 *
 * The library is stood in for by its one contract: where a key is served from and what it was
 * uploaded as. One key it has, every other it does not.
 */
final class FileItemsTest extends TestCase
{
    private const KEY = 'media/ab/cd/f00d.pdf';

    /**
     * Bound with the environment rather than in `setUp()`: the menus are a singleton that takes
     * the link addresses when it is first made, as the library's provider binds them on a site.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->instance(AssetDownloads::class, new class implements AssetDownloads
        {
            public function downloads(array $paths): array
            {
                return array_combine($paths, array_map(static fn (string $path): ?array => $path === FileItemsTest::key()
                    ? ['url' => 'https://cdn.example.test/'.$path, 'name' => 'Price list.pdf']
                    : null, $paths));
            }
        });
    }

    public static function key(): string
    {
        return self::KEY;
    }

    #[Test]
    public function a_file_item_is_printed_with_the_library_address_and_the_name_to_save_it_under(): void
    {
        $footer = $this->menu('footer');

        $this->item(['title' => ['en' => 'Prices'], 'target' => 'file', 'path' => self::KEY, 'download' => true], $footer);
        $this->item(['target' => 'file', 'path' => self::KEY], $footer);

        $tree = menu('footer');

        $this->assertSame('https://cdn.example.test/'.self::KEY, $tree[0]->url);
        $this->assertSame('Price list.pdf', $tree[0]->download);
        $this->assertSame('Price list.pdf', $tree[0]->attrs()['download']);

        // No label of its own: called what the file was uploaded as. Opened, not saved.
        $this->assertSame('Price list.pdf', $tree[1]->label);
        $this->assertNull($tree[1]->download);
        $this->assertArrayNotHasKey('download', $tree[1]->attrs());

        $html = (string) $this->blade('<x-webx-menu::menu name="footer" />');

        $this->assertStringContainsString('href="https://cdn.example.test/'.self::KEY.'"', $html);
        $this->assertStringContainsString('download="Price list.pdf"', $html);
    }

    #[Test]
    public function a_file_the_library_no_longer_has_is_left_out_like_a_draft(): void
    {
        $footer = $this->menu('footer');

        $this->item(['title' => ['en' => 'Old prices'], 'target' => 'file', 'path' => 'media/no/ne/gone.pdf'], $footer);
        $this->item(['title' => ['en' => 'Blog'], 'target' => 'url', 'url' => '/blog'], $footer);

        $this->assertSame(['Blog'], $this->labels('footer'));
    }

    #[Test]
    public function an_item_moved_off_a_file_does_not_keep_the_key(): void
    {
        $item = $this->item(['target' => 'file', 'path' => self::KEY, 'download' => true]);

        $item->fillLink(Link::fromArray(['target' => 'url', 'url' => '/blog']))->save();
        $item->refresh();

        // The library counts a key in any text column as a place the file is used.
        $this->assertNull($item->path);
        $this->assertFalse($item->download);
        $this->assertSame(LinkTarget::Url, $item->link()->target);
    }

    #[Test]
    public function the_panel_saves_a_file_item_and_reads_it_back_named(): void
    {
        $menu = $this->menu('header');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('header/items'), [
            'title' => [],
            'link' => ['target' => 'file', 'entity_type' => null, 'entity_id' => null, 'url' => null, 'hash' => null, 'new_tab' => false, 'rel' => [], 'path' => self::KEY, 'download' => true],
        ]);

        $response->assertCreated();

        $item = MenuItem::query()->where('menu_id', $menu->getKey())->firstOrFail();

        $this->assertSame(self::KEY, $item->path);
        $this->assertTrue($item->download);

        $response->assertJsonPath('data.path', self::KEY)
            ->assertJsonPath('data.download', true)
            ->assertJsonPath('data.label', 'Price list.pdf')
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.href', 'https://cdn.example.test/'.self::KEY);
    }

    #[Test]
    public function an_agent_adds_a_file_item_and_a_key_nobody_has_is_refused(): void
    {
        $this->agent('add_link', [
            'menu' => 'footer',
            'target' => 'file',
            'path' => 'media/no/ne/guess.pdf',
        ])->assertHasErrors(['There is no file media/no/ne/guess.pdf']);

        $this->assertSame(0, MenuItem::query()->count());

        $content = $this->content($this->agent('add_link', [
            'menu' => 'footer',
            'target' => 'file',
            'path' => self::KEY,
            'download' => true,
        ])->assertOk());

        $this->assertSame('file', $content['item']['target']);
        $this->assertSame(self::KEY, $content['item']['path']);
        $this->assertTrue($content['item']['download']);
        $this->assertSame('Price list.pdf', $content['item']['label']);
    }

    #[Test]
    public function deleting_a_file_of_the_library_forgets_the_built_menus(): void
    {
        $footer = $this->menu('footer');
        $this->item(['title' => ['en' => 'Blog'], 'target' => 'url', 'url' => '/blog'], $footer);

        menu('footer');
        $item = MenuItem::query()->firstOrFail();

        // Changed around the model, so only a forgotten cache shows it.
        MenuItem::query()->whereKey($item->getKey())->toBase()->update(['title' => json_encode(['en' => 'Journal'])]);

        $this->assertSame(['Blog'], $this->labels('footer'));

        // The library's model is another package's; its event is listened to by name.
        event('eloquent.deleted: WebxUi\Media\Models\MediaFile', [$item]);

        $this->assertSame(['Journal'], $this->labels('footer'));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $registry = $this->app->make(ToolRegistry::class);

        return WebxServer::tool(new RegistryTool($registry->tool('menu_'.$tool)), $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
