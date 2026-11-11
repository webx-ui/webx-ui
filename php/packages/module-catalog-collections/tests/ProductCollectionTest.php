<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Admin\Snapshots\TableGroup;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * What a collection is before anything reads it (§2): a key that never moves, a limit under the
 * ceiling, a link that can be opened, and a list that survives its products' bin.
 */
final class ProductCollectionTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function collection(array $attributes = []): ProductCollection
    {
        return ProductCollection::query()->create(['title' => 'Popular products', ...$attributes])->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertRefused(string $field, array $attributes): void
    {
        try {
            $this->collection($attributes);
        } catch (ValidationException $refusal) {
            $this->assertArrayHasKey($field, $refusal->errors());
            $this->assertSame(0, ProductCollection::withTrashed()->where('title->en', 'Popular products')->where('key', '!=', 'kept')->count());

            return;
        }

        $this->fail("The collection was saved, and [{$field}] should have refused it.");
    }

    #[Test]
    public function a_collection_made_in_code_has_the_defaults_of_the_spec(): void
    {
        $collection = $this->collection();

        $this->assertSame('popular-products', $collection->key);
        $this->assertSame('manual', $collection->source);
        $this->assertSame(8, $collection->limit);
        $this->assertSame('grid', $collection->view);
        $this->assertTrue($collection->only_purchasable);
        $this->assertTrue($collection->is_enabled);
        $this->assertFalse($collection->on_sale);
        $this->assertNull($collection->filters);
        $this->assertNull($collection->link);
    }

    #[Test]
    public function the_title_and_the_link_text_are_kept_per_language(): void
    {
        $collection = $this->collection([
            'title' => ['en' => 'New', 'ru' => 'Новинки'],
            'link_text' => ['en' => 'All new', 'ru' => 'Все новинки'],
        ]);

        $this->assertSame('Новинки', $collection->label('ru'));
        $this->assertSame('New', $collection->label('en'));
        $this->assertSame('Все новинки', $collection->getTranslation('link_text', 'ru'));
    }

    #[Test]
    public function a_collection_has_a_title(): void
    {
        $this->expectException(ValidationException::class);

        ProductCollection::query()->create(['key' => 'nameless', 'title' => ' ']);
    }

    #[Test]
    public function the_key_is_read_from_the_title_in_the_default_language_first(): void
    {
        $this->assertSame('sale', $this->collection(['title' => ['ru' => 'Скидки', 'en' => 'Sale']])->key);
        $this->assertSame('novinki', $this->collection(['title' => ['ru' => 'Новинки']])->key);
    }

    #[Test]
    public function a_title_of_no_letters_still_gives_a_key(): void
    {
        $this->assertSame('collection-2026', $this->collection(['title' => '2026'])->key);
        $this->assertSame('collection', $this->collection(['title' => '!!!'])->key);
    }

    #[Test]
    public function a_key_read_from_a_title_steps_aside_for_a_taken_one(): void
    {
        $this->collection();
        $this->collection()->delete();

        $this->assertSame('popular-products-3', $this->collection()->key);
    }

    #[Test]
    public function a_long_title_gives_a_key_that_fits_with_its_number(): void
    {
        $title = str_repeat('a', 70);

        $this->assertSame(str_repeat('a', 64), $this->collection(['title' => $title])->key);
        $this->assertSame(str_repeat('a', 62).'-2', $this->collection(['title' => $title])->key);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function typedKeys(): array
    {
        return [
            'capitals' => ['Cameras', 'cameras'],
            'an underscore' => ['top_cameras', 'top-cameras'],
            'a space' => ['top cameras', 'top-cameras'],
            'cyrillic' => ['камеры', 'kamery'],
            'a line break at the end' => ["cameras\n", 'cameras'],
            'too long' => [str_repeat('a', 65), str_repeat('a', 64)],
        ];
    }

    #[Test]
    #[DataProvider('typedKeys')]
    public function a_typed_key_is_brought_to_lowercase_latin_digits_and_dashes(string $typed, string $key): void
    {
        $this->assertSame($key, $this->collection(['key' => $typed])->key);
    }

    #[Test]
    public function a_key_that_leaves_nothing_is_made_of_the_title_and_one_that_is_not_text_is_refused(): void
    {
        $this->assertSame('sale', $this->collection(['title' => 'Sale', 'key' => '!!!'])->key);

        $this->assertRefused('key', ['key' => ['cameras']]);
    }

    #[Test]
    public function a_key_of_ones_own_is_kept_as_written(): void
    {
        $this->assertSame('top-10', $this->collection(['key' => 'top-10'])->key);
    }

    #[Test]
    public function a_taken_key_is_refused_rather_than_changed(): void
    {
        $this->collection(['key' => 'kept']);

        $this->assertRefused('key', ['key' => 'kept']);
    }

    #[Test]
    public function a_key_in_the_bin_is_still_taken(): void
    {
        $this->collection(['key' => 'kept'])->delete();

        $this->assertRefused('key', ['key' => 'kept']);
    }

    #[Test]
    public function a_key_deleted_for_good_is_free_again(): void
    {
        $collection = $this->collection(['key' => 'kept']);
        $collection->delete();
        $collection->forceDelete();

        $this->assertSame('kept', $this->collection(['key' => 'kept'])->key);
    }

    #[Test]
    public function a_key_never_changes_after_creation(): void
    {
        $collection = $this->collection(['key' => 'kept']);
        $collection->key = 'moved';

        try {
            $collection->save();
            $this->fail('The key moved.');
        } catch (ValidationException $refusal) {
            $this->assertArrayHasKey('key', $refusal->errors());
        }

        $this->assertSame('kept', $collection->fresh()?->key);
    }

    #[Test]
    public function restoring_a_collection_finds_its_key_free(): void
    {
        $collection = $this->collection(['key' => 'kept']);
        $collection->delete();

        $this->assertNull(ProductCollection::query()->where('key', 'kept')->first());

        $collection->restore();

        $this->assertSame('kept', ProductCollection::query()->where('key', 'kept')->first()?->key);
    }

    #[Test]
    public function the_limit_goes_up_to_the_ceiling(): void
    {
        $this->assertSame(1, $this->collection(['limit' => 1])->limit);
        $this->assertSame(48, $this->collection(['limit' => 48])->limit);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function badLimits(): array
    {
        return [
            'over the ceiling' => [49],
            'zero' => [0],
            'below zero' => [-1],
            'nothing' => [null],
            'words' => ['many'],
        ];
    }

    #[Test]
    #[DataProvider('badLimits')]
    public function a_limit_out_of_range_is_refused(mixed $limit): void
    {
        $this->assertRefused('limit', ['limit' => $limit]);
    }

    #[Test]
    public function the_refusal_names_the_ceiling(): void
    {
        try {
            $this->collection(['limit' => 49]);
            $this->fail('A limit over the ceiling was saved.');
        } catch (ValidationException $refusal) {
            $this->assertStringContainsString('48', (string) ($refusal->errors()['limit'][0] ?? ''));
        }
    }

    #[Test]
    public function an_edit_cannot_raise_the_limit_over_the_ceiling(): void
    {
        $collection = $this->collection();

        $this->expectException(ValidationException::class);

        $collection->update(['limit' => 49]);
    }

    #[Test]
    public function the_ceiling_is_the_sites_to_set(): void
    {
        config(['webx-catalog-collections.max_limit' => 60]);

        $this->assertSame(60, $this->collection(['limit' => 60])->limit);
    }

    #[Test]
    public function a_collection_under_an_old_ceiling_can_still_be_restored(): void
    {
        $collection = $this->collection(['limit' => 40]);
        $collection->delete();

        config(['webx-catalog-collections.max_limit' => 12]);

        $this->assertTrue($collection->restore());
        $this->assertSame(40, $collection->fresh()?->limit);
    }

    #[Test]
    public function a_link_is_kept_as_the_link_field_keeps_it(): void
    {
        $collection = $this->collection(['link' => ['target' => 'url', 'url' => '/sale']]);

        $this->assertSame('url', $collection->link['target'] ?? null);
        $this->assertSame('/sale', $collection->link['url'] ?? null);
    }

    #[Test]
    public function a_link_to_nowhere_is_no_link(): void
    {
        $this->assertNull($this->collection(['link' => ['target' => 'url', 'url' => '']])->link);
        $this->assertNull($this->collection(['link' => ['target' => 'none']])->link);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function badLinks(): array
    {
        return [
            'javascript' => [['target' => 'url', 'url' => 'javascript:alert(1)']],
            'javascript in capitals' => [['target' => 'url', 'url' => 'JavaScript:alert(1)']],
            'javascript behind spaces' => [['target' => 'url', 'url' => '  javascript:alert(1)']],
            'data' => [['target' => 'url', 'url' => 'data:text/html,<script>alert(1)</script>']],
            'no such target' => [['target' => 'somewhere', 'url' => '/sale']],
            'a section nobody registered' => [['target' => 'entity', 'entity_type' => 'nothing', 'entity_id' => 1]],
            'a field the link does not have' => [['target' => 'url', 'url' => '/sale', 'onclick' => 'alert(1)']],
            'not a link at all' => ['javascript:alert(1)'],
        ];
    }

    #[Test]
    #[DataProvider('badLinks')]
    public function a_link_that_cannot_be_opened_is_refused(mixed $link): void
    {
        $this->assertRefused('link', ['link' => $link]);
    }

    #[Test]
    public function an_edit_cannot_put_a_script_into_the_link(): void
    {
        $collection = $this->collection();

        try {
            $collection->update(['link' => ['target' => 'url', 'url' => 'javascript:alert(1)']]);
            $this->fail('The link was saved.');
        } catch (ValidationException $refusal) {
            $this->assertArrayHasKey('link', $refusal->errors());
        }

        $this->assertNull($collection->fresh()?->link);
    }

    #[Test]
    public function a_source_and_a_view_come_from_their_lists(): void
    {
        $this->assertRefused('source', ['source' => 'mixed']);
        $this->assertRefused('view', ['view' => 'slider']);
        $this->assertSame('carousel', $this->collection(['source' => 'filter', 'view' => 'carousel'])->view);
    }

    #[Test]
    public function the_set_is_kept_normalised(): void
    {
        $collection = $this->collection([
            'source' => 'filter',
            'filters' => ['p.7' => ['values' => ['12', '3']], 'empty' => ['values' => []]],
        ]);

        $this->assertSame(['p.7' => ['values' => ['3', '12']]], $collection->filters);
        $this->assertSame(['p.7'], $collection->set()->keys());
    }

    #[Test]
    public function a_category_that_is_gone_stays_on_the_collection(): void
    {
        $collection = $this->collection(['source' => 'filter', 'category_id' => 999]);

        $this->assertSame(999, $collection->category_id);
        $this->assertNull($collection->category);
    }

    #[Test]
    public function the_list_comes_in_its_order_and_outlives_the_bin_of_a_product(): void
    {
        $first = $this->product('First');
        $second = $this->product('Second');
        $collection = $this->collection();
        $collection->products()->attach([$first->id => ['position' => 2], $second->id => ['position' => 1]]);

        $this->assertSame([$second->id, $first->id], $collection->products()->pluck('catalog_products.id')->all());

        $first->delete();

        $this->assertSame([$second->id], $collection->products()->pluck('catalog_products.id')->all());
        $this->assertSame(2, DB::table(ProductCollection::PRODUCTS)->where('collection_id', $collection->id)->count());

        $first->restore();

        $this->assertSame([$second->id, $first->id], $collection->products()->pluck('catalog_products.id')->all());
    }

    #[Test]
    public function a_collection_gone_for_good_takes_its_rows_with_it(): void
    {
        $collection = $this->collection();
        $collection->products()->attach($this->product('First')->id);

        $collection->forceDelete();

        $this->assertSame(0, DB::table(ProductCollection::PRODUCTS)->count());
    }

    #[Test]
    public function a_collection_moves_between_stands_as_content(): void
    {
        $tables = $this->app->make(SnapshotTables::class);

        $this->assertSame(TableGroup::Content, $tables->groupOf('catalog_collections'));
        $this->assertSame(TableGroup::Content, $tables->groupOf('catalog_collection_products'));
    }

    #[Test]
    public function the_journal_knows_a_collection_behind_the_catalogues_permissions(): void
    {
        $type = $this->app->make(HistoryTypes::class)->find(ProductCollection::TYPE);

        $this->assertNotNull($type);
        $this->assertSame(ProductCollection::class, $type->model);
        $this->assertSame(['catalog.view', 'catalog.manage', 'catalog.delete'], $type->permissions);
    }

    #[Test]
    public function the_journal_writes_what_an_edit_changed(): void
    {
        $collection = $this->collection();
        $collection->update(['limit' => 12]);

        $entries = HistoryEntry::query()
            ->where('subject_type', ProductCollection::TYPE)
            ->where('subject_id', $collection->id)
            ->orderBy('id')
            ->get();

        $this->assertSame([HistoryEntry::CREATED, HistoryEntry::UPDATED], $entries->pluck('event')->all());
        $this->assertSame(['limit'], array_column($entries[1]->changes ?? [], 'field'));
    }

    #[Test]
    public function the_migration_rolls_back_and_comes_up_again(): void
    {
        $migration = require dirname(__DIR__).'/database/migrations/2026_01_06_000000_create_catalog_collections_tables.php';

        $migration->down();

        $this->assertFalse(Schema::hasTable('catalog_collections'));
        $this->assertFalse(Schema::hasTable('catalog_collection_products'));
        $this->assertTrue(Schema::hasTable('catalog_products'));

        $migration->up();

        $this->assertSame('popular-products', $this->collection()->key);
    }

    #[Test]
    public function the_index_of_the_old_price_rolls_back_and_comes_up_again(): void
    {
        $migration = require dirname(__DIR__).'/database/migrations/2026_01_06_000001_add_old_price_index_to_catalog_products_table.php';
        $this->assertTrue(Schema::hasIndex('catalog_products', ['old_price']));

        $migration->down();
        $this->assertFalse(Schema::hasIndex('catalog_products', ['old_price']));

        $migration->up();
        $this->assertTrue(Schema::hasIndex('catalog_products', ['old_price']));
    }
}
