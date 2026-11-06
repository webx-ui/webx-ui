<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * The tag on a page (§4.1, §4.4, §5.3, §5.4): what it prints, what its arguments change, and that
 * every case with nothing to show prints nothing at all.
 */
final class CollectionComponentTest extends TestCase
{
    /**
     * @param  list<Product>  $products
     * @param  array<string, mixed>  $attributes
     */
    private function collection(array $products, array $attributes = []): ProductCollection
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks', ...$attributes])->refresh();

        foreach ($products as $position => $product) {
            $collection->products()->attach($product->id, ['position' => $position]);
        }

        // The list is written past the model here, and a path with no save moves the group itself.
        $collection->touch();

        return $collection;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $tag = '<x-webx-catalog-collections::collection key="picks" />', array $data = []): string
    {
        return trim(Blade::render($tag, $data));
    }

    #[Test]
    public function a_collection_prints_its_heading_and_the_cards_of_the_catalogue_in_its_order(): void
    {
        $this->collection([$this->product('Second by name'), $this->product('A first by name')]);

        $html = $this->render();

        $this->assertStringContainsString('class="webx-catalog-collection webx-catalog-collection--grid" data-collection="picks"', $html);
        $this->assertStringContainsString('<h2 class="webx-catalog-collection__title">Picks</h2>', $html);
        $this->assertStringContainsString('<ul class="webx-catalog-grid webx-catalog-collection__grid">', $html);
        $this->assertSame(2, substr_count($html, 'class="webx-catalog-card"'));
        $this->assertLessThan(strpos($html, 'A first by name'), strpos($html, 'Second by name'));
    }

    #[Test]
    public function every_card_has_its_button(): void
    {
        $this->collection([$this->product('Priced'), $this->product('Asked', ['price' => null])]);

        $html = $this->render();

        $this->assertStringContainsString(__('webx-catalog::storefront.buy'), $html);
        $this->assertStringContainsString(__('webx-catalog::storefront.ask-price'), $html);
    }

    #[Test]
    public function the_carousel_is_the_slider_of_the_widgets(): void
    {
        $this->collection([$this->product('One'), $this->product('Two')], ['view' => ProductCollection::CAROUSEL]);

        $html = $this->render();

        $this->assertStringContainsString('webx-catalog-collection--carousel', $html);
        $this->assertStringContainsString('webx-slider--cards', $html);
        $this->assertSame(2, substr_count($html, 'webx-catalog-collection__item'));
        $this->assertStringNotContainsString('<ul', $html);
    }

    #[Test]
    public function the_view_of_a_place_goes_over_the_collections_and_an_unknown_one_is_dropped(): void
    {
        $this->collection([$this->product('One')]);

        $this->assertStringContainsString('--carousel', $this->render('<x-webx-catalog-collections::collection key="picks" view="carousel" />'));
        $this->assertStringContainsString('--grid', $this->render('<x-webx-catalog-collections::collection key="picks" view="wall" />'));
    }

    #[Test]
    public function the_limit_of_a_place_only_lowers_the_collections(): void
    {
        $this->collection([$this->product('One'), $this->product('Two'), $this->product('Three')], ['limit' => 2]);
        $cards = fn (string $limit): int => substr_count($this->render('<x-webx-catalog-collections::collection key="picks" '.$limit.' />'), 'class="webx-catalog-card"');

        $this->assertSame(2, $cards(''));
        $this->assertSame(1, $cards(':limit="1"'));
        $this->assertSame(1, $cards('limit="1"'));
        $this->assertSame(2, $cards(':limit="40"'));
        $this->assertSame(1, $cards(':limit="0"'), 'A number below one is held at one.');
        $this->assertSame(2, $cards('limit=""'), 'The empty field of a block is no limit.');
        $this->assertSame(2, $cards('limit="many"'));
    }

    #[Test]
    public function the_title_of_a_place_replaces_the_heading_and_a_dash_removes_it(): void
    {
        $this->collection([$this->product('One')]);

        $this->assertStringContainsString('>For you</h2>', $this->render('<x-webx-catalog-collections::collection key="picks" title="For you" />'));

        $html = $this->render('<x-webx-catalog-collections::collection key="picks" title="-" />');

        $this->assertStringNotContainsString('webx-catalog-collection__title', $html);
        $this->assertStringNotContainsString('webx-catalog-collection__head', $html);
    }

    #[Test]
    public function the_title_is_read_in_the_language_of_the_page(): void
    {
        $this->collection([$this->product('One', ['slug' => ['en' => 'one', 'ru' => 'odin']])], ['title' => ['en' => 'Picks', 'ru' => 'Выбор']]);

        $this->app->setLocale('ru');

        $this->assertStringContainsString('>Выбор</h2>', $this->render());
    }

    #[Test]
    public function what_a_place_gives_is_printed_as_text(): void
    {
        $this->collection([$this->product('One')]);

        $html = $this->render('<x-webx-catalog-collections::collection key="picks" title="<script>x</script>" />');

        $this->assertStringNotContainsString('<script>x', $html);
    }

    #[Test]
    public function the_link_says_all_products_unless_it_has_words_of_its_own(): void
    {
        $link = ['target' => 'url', 'url' => '/catalog/new'];
        $collection = $this->collection([$this->product('One')], ['link' => $link]);

        $this->assertStringContainsString('/catalog/new"', $this->render());
        $this->assertStringContainsString('>All products</a>', $this->render());

        $collection->update(['link_text' => 'Everything new']);

        $this->assertStringContainsString('>Everything new</a>', $this->render());
    }

    #[Test]
    public function a_link_to_something_not_on_the_site_is_not_printed(): void
    {
        $this->collection([$this->product('One')], ['link' => ['target' => 'url', 'url' => '/somewhere']]);

        // A value from before the check, or written into the column: the way out checks it again.
        ProductCollection::query()->where('key', 'picks')->toBase()->update(['link' => json_encode(['target' => 'url', 'url' => 'javascript:alert(1)'])]);
        ProductCollection::query()->where('key', 'picks')->firstOrFail()->touch();

        $html = $this->render();

        $this->assertStringContainsString('class="webx-catalog-collection ', $html);
        $this->assertStringNotContainsString('webx-catalog-collection__all', $html);
    }

    #[Test]
    public function a_link_to_a_product_taken_off_the_site_is_not_printed(): void
    {
        $target = $this->product('Target');
        $this->collection([$this->product('One')], ['link' => ['target' => 'entity', 'entity_type' => Product::TYPE, 'entity_id' => $target->id]]);

        $this->assertStringContainsString('webx-catalog-collection__all', $this->render());

        $target->update(['is_published' => false]);

        $this->assertStringNotContainsString('webx-catalog-collection__all', $this->render());
    }

    #[Test]
    public function a_collection_with_nothing_to_show_prints_nothing(): void
    {
        $this->collection([], ['key' => 'empty']);
        $this->collection([$this->product('One')], ['key' => 'off', 'is_enabled' => false]);
        $this->collection([$this->product('Two')], ['key' => 'binned'])->delete();
        $this->collection([$this->product('Three', ['is_published' => false])], ['key' => 'hidden']);

        foreach (['empty', 'off', 'binned', 'hidden', 'unknown'] as $key) {
            $this->assertSame('', $this->render('<x-webx-catalog-collections::collection key="'.$key.'" />'), $key);
        }

        $this->assertSame('', $this->render('<x-webx-catalog-collections::collection />'));
    }

    #[Test]
    public function a_collection_deleted_for_good_prints_nothing_and_a_new_one_takes_its_place(): void
    {
        $tag = '<x-webx-catalog-collections::collection key="picks" />';
        $collection = $this->collection([$this->product('One')], ['key' => 'picks']);

        $this->assertStringContainsString('One', $this->render($tag));

        $collection->delete();
        $collection->forceDelete();

        $this->assertSame('', $this->render($tag));

        $this->collection([$this->product('Two')], ['key' => 'picks']);

        $this->assertStringContainsString('Two', $this->render($tag));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keysOfAnotherShape(): iterable
    {
        yield 'capitals' => ['PICKS'];
        yield 'spaces around' => [' picks '];
        yield 'a line after it' => ["picks\n"];
        yield 'markup' => ['<b>picks</b>'];
    }

    #[Test]
    #[DataProvider('keysOfAnotherShape')]
    public function a_key_of_another_shape_prints_nothing(string $key): void
    {
        $this->collection([$this->product('One')]);

        $this->assertSame('', $this->render('<x-webx-catalog-collections::collection :key="$key" />', ['key' => $key]));
    }

    #[Test]
    public function a_product_taken_off_the_site_leaves_a_warm_collection_at_once(): void
    {
        $gone = $this->product('Gone');
        $this->collection([$this->product('Stays'), $gone]);
        $this->render();

        // Past every event, so the cached ids still name it: the second look at who may be seen.
        Product::query()->whereKey($gone->id)->toBase()->update(['is_published' => false]);

        $html = $this->render();

        $this->assertStringContainsString('Stays', $html);
        $this->assertStringNotContainsString('Gone', $html);
    }
}
