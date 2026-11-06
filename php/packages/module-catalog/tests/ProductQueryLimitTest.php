<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;

/**
 * A shelf costs what it shows: `products()->take(8)` on a large catalogue loads eight products,
 * and they are still the first eight a reader of this language may see.
 */
final class ProductQueryLimitTest extends TestCase
{
    private Category $shelf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLocales('en', 'ru');
        $this->shelf = $this->category('shelf');
    }

    #[Test]
    public function a_limit_loads_that_many_products_of_a_thousand(): void
    {
        $this->insert(1000);
        $loaded = $this->countLoaded();

        DB::enableQueryLog();
        $products = products()->sort('popular')->take(8)->models();
        $selects = array_filter(
            array_column(DB::getQueryLog(), 'query'),
            static fn (string $sql): bool => str_contains($sql, 'from "catalog_products"') && str_contains($sql, 'select "catalog_products".*'),
        );

        $this->assertCount(8, $products);
        $this->assertSame(8, $loaded->count);
        $this->assertNotEmpty($selects);
        $this->assertStringContainsString('limit 8', implode(' ', $selects));
    }

    #[Test]
    public function no_limit_still_lists_everything(): void
    {
        $this->insert(30);

        $this->assertCount(30, products()->models());
    }

    #[Test]
    public function the_limit_counts_what_is_written_in_the_language(): void
    {
        // The first in the order have no address in Russian: a limit before the language would
        // come back short, or empty.
        $this->insert(5, static fn (int $i): array => ['en' => "only-en-{$i}"], priority: 100);
        $this->insert(5, static fn (int $i): array => ['en' => "both-{$i}", 'ru' => "oba-{$i}"]);

        $russian = products()->locale('ru')->take(3)->models();

        $this->assertCount(3, $russian);
        $this->assertTrue($russian->every(static fn (Product $product): bool => $product->hasUrlIn('ru')));
        $this->assertCount(3, products()->locale('en')->take(3)->models());
        $this->assertCount(5, products()->locale('ru')->models());
        $this->assertCount(10, products()->locale('en')->models());
    }

    #[Test]
    public function an_empty_or_null_slug_is_no_address(): void
    {
        $this->insert(1, static fn (): array => ['en' => 'kept', 'ru' => 'ostalsya']);
        $this->insert(1, static fn (): array => ['en' => 'blank', 'ru' => '']);
        $this->insert(1, static fn (): array => ['en' => 'nulled', 'ru' => null]);

        $this->assertSame(['ostalsya'], products()->locale('ru')->models()->map(
            static fn (Product $product): mixed => $product->getTranslation('slug', 'ru', false),
        )->all());
    }

    #[Test]
    public function a_limit_over_chosen_products_counts_in_the_order_chosen(): void
    {
        $ids = $this->insert(5);

        // A limit in SQL would cut by the sort first, and the chosen order would be lost.
        $chosen = [$ids[4], $ids[0], $ids[2]];

        $this->assertSame([$ids[4], $ids[0]], products()->only($chosen)->take(2)->models()->modelKeys());
    }

    #[Test]
    public function the_product_out_of_the_visible_ones_does_not_take_a_place(): void
    {
        $ids = $this->insert(3);
        Product::query()->whereKey($ids[0])->update(['is_published' => false]);

        $this->assertCount(2, products()->take(3)->models());
    }

    /**
     * Rows rather than models: a thousand saves would write a thousand addresses for nothing.
     *
     * @param  (callable(int): array<string, string|null>)|null  $slug
     * @return list<int>
     */
    private function insert(int $count, ?callable $slug = null, int $priority = 0): array
    {
        static $serial = 0;
        $slug ??= static fn (int $i): array => ['en' => "item-{$i}"];
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $serial++;
            $rows[] = [
                'name' => json_encode(['en' => "Item {$serial}"], JSON_THROW_ON_ERROR),
                'slug' => json_encode($slug($serial), JSON_THROW_ON_ERROR),
                'category_id' => $this->shelf->getKey(),
                'is_published' => true,
                'priority' => $priority,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('catalog_products')->insert($chunk);
        }

        /** @var list<int> $ids */
        $ids = DB::table('catalog_products')->orderByDesc('id')->limit($count)->pluck('id')->map(intval(...))->reverse()->values()->all();

        return $ids;
    }

    /** Every product turned into a model from here on, whichever query it came from. */
    private function countLoaded(): object
    {
        $loaded = new class
        {
            public int $count = 0;
        };

        Event::listen('eloquent.retrieved: '.Product::class, static function () use ($loaded): void {
            $loaded->count++;
        });

        return $loaded;
    }
}
