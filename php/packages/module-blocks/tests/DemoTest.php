<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Demo\BlocksDemo;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\Region;
use WebxUi\Menu\MenuCache;
use WebxUi\Menu\MenuServiceProvider;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The demo content: three types to build pages out of, and — where the layout declares them — a
 * header and a footer made of blocks, published (§9 of the regions spec).
 *
 * Seeded by hand rather than through `webx:demo`, like the other modules' demo tests: what the
 * plan buys is covered where the plan lives. What is covered here is what the journal says, and
 * that playing it backwards gives the site its header from code again.
 */
final class DemoTest extends RegionTestCase
{
    /**
     * The development root autoloads every package's helpers, so `menu()` and `settings()` exist
     * here whatever the providers — and the templates, which ask `function_exists()`, call them.
     * With their providers they answer the way they do on a site that has both modules.
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NestedSetServiceProvider::class,
            ...parent::getPackageProviders($app),
            SettingsServiceProvider::class,
            MenuServiceProvider::class,
        ];
    }

    #[Test]
    public function it_publishes_a_header_and_a_footer_made_of_blocks(): void
    {
        $this->seeded();

        $this->assertSame(['columns', 'demo-footer', 'demo-header', 'hero', 'text'], Block::query()->orderBy('slug')->pluck('slug')->all());

        foreach (['header' => 'demo-header', 'footer' => 'demo-footer'] as $name => $type) {
            $region = Region::query()->where('name', $name)->firstOrFail();

            $this->assertTrue($region->isPublished());
            $this->assertSame($type, $region->blocksTree()[0]['type']);
        }

        $html = $this->tag();

        $this->assertStringNotContainsString('Header from code', $html);
        $this->assertStringContainsString('class="b-demo-header"', $html);
        // Nothing in the settings yet is the application's name, and an empty menu is no menu —
        // neither is a failure that sends the region to its fallback.
        $this->assertStringContainsString('>'.config('app.name').'</a>', $html);
        $this->assertStringContainsString('Get in touch', $html);
        $this->assertMatchesRegularExpression('#^<link rel="stylesheet" href="[^"]+\.css">#', $html);

        $this->assertStringContainsString('&copy; '.date('Y'), $this->tag('name="footer"'));
    }

    #[Test]
    public function the_header_reads_the_name_and_the_menus_of_the_site_as_they_are_now(): void
    {
        $this->seeded();

        // After the demo, not before: nothing about the site is copied into the region.
        $this->app->make(Settings::class)->save(['general.project-name' => 'Bakery']);
        $item = new MenuItem([
            'menu_id' => Menu::query()->firstOrCreate(['key' => 'header'], ['title' => ['en' => 'Header']])->getKey(),
            'target' => 'url',
            'url' => '/prices',
            'title' => ['en' => 'Prices'],
        ]);
        $item->saveAsRoot();
        $this->app->make(MenuCache::class)->flush();

        $html = $this->tag();

        $this->assertStringContainsString('>Bakery</a>', $html);
        $this->assertMatchesRegularExpression('#<a class="b-demo-header__link\s*"[^>]*href="[^"]*/prices"[^>]*>Prices</a>#', $html);
    }

    #[Test]
    public function removing_it_brings_back_the_header_from_code(): void
    {
        $ledger = $this->seeded();

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, Region::query()->count());
        $this->assertSame(0, Block::query()->count());
        // The history is rows of its own; nothing points at a region that is gone.
        $this->assertSame(0, EntityVersion::query()->where('versionable_type', (new Region)->getMorphClass())->count());

        $this->assertStringContainsString('Header from code', $this->tag());
    }

    #[Test]
    public function a_region_somebody_has_saved_is_left_alone(): void
    {
        $this->publish('bar', '<div class="b-bar">{{ $text }}</div>');
        $this->region('header', [$this->node('bar', ['text' => 'Theirs'])]);

        $ledger = $this->seeded();

        $this->assertStringContainsString('Theirs', $this->tag());
        $this->assertSame(['footer region'], array_values(array_map(
            static fn (array $entry): string => (string) $entry['label'],
            array_filter($ledger->entries(), static fn (array $entry): bool => $entry['type'] === Region::class),
        )));
        // The header's type is made only for a header the demo fills.
        $this->assertFalse(Block::query()->where('slug', 'demo-header')->exists());
    }

    #[Test]
    public function without_declared_regions_it_says_so_and_leaves_the_layout_alone(): void
    {
        $this->app['config']->set('webx-blocks.regions', []);

        $ledger = $this->seeded();

        $this->assertSame(0, Region::query()->count());
        $this->assertFalse(Block::query()->where('slug', 'demo-header')->exists());
        $this->assertCount(1, $ledger->takeNotes());
    }

    private function seeded(): DemoLedger
    {
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('blocks');

        $this->app->make(BlocksDemo::class)->seed($ledger);

        return $ledger;
    }
}
