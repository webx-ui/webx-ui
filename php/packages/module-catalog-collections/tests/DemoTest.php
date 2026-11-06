<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Demo\BlocksDemo;
use WebxUi\Blog\BlogServiceProvider;
use WebxUi\Blog\Demo\BlogDemo;
use WebxUi\Blog\Models\Article;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\CatalogCollections\Demo\CollectionsDemo;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Panel\CollectionsModule;
use WebxUi\CatalogCollections\Storefront\CollectionProducts;
use WebxUi\CatalogLabels\Demo\LabelsDemo;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\Pages\Demo\PagesDemo;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\PagesServiceProvider;

/**
 * `webx:demo` of the collections (§9): every way of filling one, placed on the home page and in an
 * article, and all of it gone again with the journal.
 */
final class DemoTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            BlocksServiceProvider::class,
            PagesServiceProvider::class,
            BlogServiceProvider::class,
            LabelsServiceProvider::class,
            ...parent::getPackageProviders($app),
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The demo's pictures go in as they are: what the pipeline makes of an upload is the media
        // module's test.
        $app['config']->set('webx-media.optimize.enabled', false);
        $app['config']->set('webx-blocks.entities', [Page::class, Article::class]);
    }

    private function seeded(): DemoLedger
    {
        Storage::fake('public');
        $this->artisan('webx:blocks:offered', ['--install' => true, '--no-interaction' => true]);
        $ledger = $this->app->make(DemoLedger::class);

        foreach ([
            'blocks' => BlocksDemo::class,
            'catalog' => CatalogDemo::class,
            'catalog-labels' => LabelsDemo::class,
            'pages' => PagesDemo::class,
            'articles' => BlogDemo::class,
            'catalog-collections' => CollectionsDemo::class,
        ] as $id => $demo) {
            $ledger->forModule($id);
            $this->app->make($demo)->seed($ledger);
        }

        return $ledger;
    }

    #[Test]
    public function what_it_stands_on_is_asked_for_only_when_installed(): void
    {
        $module = $this->app->make(ModuleRegistry::class)->get('catalog-collections');

        $this->assertInstanceOf(CollectionsModule::class, $module);
        $this->assertSame(['catalog', 'catalog-labels', 'pages', 'articles'], $module->requires());
    }

    #[Test]
    public function every_way_of_filling_a_collection_is_on_the_demo_shop(): void
    {
        $this->seeded();

        $collections = ProductCollection::query()->get()->keyBy('key');

        $this->assertSame(
            ['bestsellers', 'coming-soon', 'new-arrivals', 'on-sale', 'seasonal-offer', 'starter-wardrobe'],
            $collections->keys()->sort()->values()->all(),
        );
        $this->assertSame(ProductCollection::CAROUSEL, $collections['on-sale']->view);
        $this->assertFalse($collections['seasonal-offer']->is_enabled);
        $this->assertSame(4, $collections['starter-wardrobe']->products()->count());
        $this->assertNotNull($collections['starter-wardrobe']->link);
        $this->assertSame(0, $collections['coming-soon']->products()->count());

        $products = $this->app->make(CollectionProducts::class);

        foreach (['new-arrivals', 'on-sale', 'bestsellers', 'starter-wardrobe'] as $key) {
            $this->assertNotSame([], $products->ids($collections[$key]), $key.' shows nothing');
        }
    }

    #[Test]
    public function the_home_page_and_an_article_carry_them_and_get_them_back_on_removal(): void
    {
        $ledger = $this->seeded();

        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);
        $placed = array_column(array_filter($home->blocksTree(), static fn (array $block): bool => $block['type'] === 'product-collection'), 'values');
        $this->assertSame(['new-arrivals', 'on-sale', 'bestsellers', 'seasonal-offer'], array_column($placed, 'collection'));
        $this->assertFalse($home->hasDraft());

        $article = Article::query()->whereKey($ledger->idsOf('articles', Article::class))->where('published_at', '<=', now())->orderBy('id')->firstOrFail();
        $this->assertStringContainsString('<p>[product-collection key=starter-wardrobe]</p>', (string) json_encode($article->blocksTree(), JSON_UNESCAPED_SLASHES));
        $this->assertFalse($article->hasDraft());

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, ProductCollection::withTrashed()->count());
        $this->assertSame(0, Article::withTrashed()->count());
        $this->assertStringNotContainsString('product-collection', (string) json_encode(Page::home()?->blocksTree()));
    }

    #[Test]
    public function a_site_with_collections_of_its_own_is_left_alone(): void
    {
        ProductCollection::query()->create(['title' => 'Ours', 'key' => 'ours']);

        $ledger = $this->seeded();

        $this->assertSame(['ours'], ProductCollection::query()->pluck('key')->all());
        $this->assertStringContainsString('left them alone', implode(' ', $ledger->takeNotes()));
    }
}
