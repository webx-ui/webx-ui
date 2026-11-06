<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Relations\CollectionTarget;
use WebxUi\CatalogCollections\Screens\CollectionFieldType;

/**
 * The block `product-collection` (§4.2): installed by `webx:blocks:offered`, drawn on its own sample
 * (no collection, nothing), a key refused only when it is written, the picker behind the field, and
 * the same block standing in a region of the layout.
 */
final class BlockTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [BlocksServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-blocks.regions', ['footer' => ['title' => 'Footer']]);
    }

    #[Test]
    public function the_offered_type_is_installed_with_its_four_fields(): void
    {
        $block = $this->installBlock()->load('publishedVersion');

        $this->assertSame('Product collection', $block->title);
        $this->assertSame(['collection', 'title', 'view', 'limit'], array_column((array) $block->publishedVersion?->schema, 'id'));
        $this->assertSame(['wx-catalog-collection', 'wx-input', 'wx-select', 'wx-input-number'], array_column((array) $block->publishedVersion?->schema, 'type'));

        // The sample names no collection: a key would be copied into every new block.
        $this->assertNull($block->publishedVersion?->sample['collection'] ?? null);
    }

    #[Test]
    public function it_draws_the_collection_with_what_the_block_says_over_it(): void
    {
        $this->installBlock();
        $this->collection(3);

        $html = $this->render(['collection' => 'picks', 'title' => 'For the workshop', 'view' => 'carousel', 'limit' => 2]);

        $this->assertStringContainsString('<div class="b-product-collection" data-wx-block="product-collection">', $html);
        $this->assertStringContainsString('webx-catalog-collection--carousel', $html);
        $this->assertStringContainsString('For the workshop', $html);
        $this->assertStringNotContainsString('Picks', $html);
        $this->assertSame(2, substr_count($html, 'class="webx-catalog-card"'));
    }

    #[Test]
    public function empty_fields_leave_the_collection_as_it_is_and_a_dash_takes_the_heading_away(): void
    {
        $this->installBlock();
        $this->collection(3);

        $html = $this->render(['collection' => 'picks', 'title' => '', 'view' => null, 'limit' => null]);

        $this->assertStringContainsString('webx-catalog-collection--grid', $html);
        $this->assertStringContainsString('<h2 class="webx-catalog-collection__title">Picks</h2>', $html);
        $this->assertSame(3, substr_count($html, 'class="webx-catalog-card"'));

        $this->assertStringNotContainsString('webx-catalog-collection__title', $this->render(['collection' => 'picks', 'title' => '-']));
    }

    #[Test]
    public function a_limit_of_the_block_only_lowers_the_limit_of_the_collection(): void
    {
        $this->installBlock();
        $this->collection(3, ['limit' => 2]);

        $this->assertSame(2, substr_count($this->render(['collection' => 'picks', 'limit' => 40]), 'class="webx-catalog-card"'));
    }

    #[Test]
    public function a_collection_with_nothing_to_show_leaves_the_block_without_its_cards(): void
    {
        $this->installBlock();
        $collection = $this->collection(1);

        $collection->delete();
        $this->assertStringNotContainsString('webx-catalog-collection', $this->render(['collection' => 'picks']));

        $collection->restore();
        $this->assertStringContainsString('webx-catalog-collection', $this->render(['collection' => 'picks']), 'the block comes back with it');

        $collection->update(['is_enabled' => false]);
        $this->assertStringNotContainsString('webx-catalog-collection', $this->render(['collection' => 'picks']));

        $this->assertSame('', trim($this->render([])), 'no collection chosen, no wrapper either');
    }

    #[Test]
    public function the_type_is_registered_and_keeps_the_key(): void
    {
        $type = $this->app->make(FieldTypes::class)->get('wx-catalog-collection');

        $this->assertInstanceOf(CollectionFieldType::class, $type);
        $this->assertSame('picks', $type->store('  picks ', []));
        $this->assertNull($type->store('', []), 'empty is none');
        $this->assertNull($type->store(null, []));
        $this->assertSame('picks', $type->resolve('picks', []));
        $this->assertNull($type->resolve(['en' => 'picks'], []));
    }

    #[Test]
    public function a_key_nobody_has_is_a_problem_that_names_the_keys_there_are(): void
    {
        $type = new CollectionFieldType;

        $this->assertSame(['There is no collection «picks»: the site has none yet.'], $type->newValueProblems('picks', []));

        ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks']);
        ProductCollection::query()->create(['title' => 'Off', 'key' => 'off', 'is_enabled' => false]);

        $this->assertSame([], $type->newValueProblems('picks', []));
        $this->assertSame([], $type->newValueProblems('off', []), 'a collection that is off is still a collection');
        $this->assertSame([], $type->newValueProblems('', []));
        $this->assertSame(['There is no collection «pick». There are: picks, off.'], $type->newValueProblems('pick', []));
    }

    #[Test]
    public function a_collection_in_the_bin_is_not_offered_to_a_new_value(): void
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks']);
        ProductCollection::query()->create(['title' => 'Other', 'key' => 'other']);
        $collection->delete();

        $this->assertSame(
            ['There is no collection «picks». There are: other.'],
            (new CollectionFieldType)->newValueProblems('picks', []),
        );
    }

    #[Test]
    public function saving_a_block_refuses_a_new_unknown_key_and_keeps_one_it_already_held(): void
    {
        $this->installBlock();
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks']);
        $region = Region::query()->create(['name' => 'footer']);
        $tree = [$this->node(['collection' => 'picks'])];

        $region->saveDraft(['blocks' => $region->storeBlocks($tree)]);

        try {
            $region->storeBlocks([$this->node(['collection' => 'gone'])]);
            $this->fail('A key nobody has was accepted.');
        } catch (ValidationException $refused) {
            $this->assertStringContainsString('There is no collection «gone». There are: picks.', json_encode($refused->errors(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        }

        // Binned after the region was saved: the same block goes on saving, and no other can take it.
        $collection->delete();

        $this->assertSame('picks', $region->storeBlocks($tree)[0]['values']['collection']);

        // Deleted for good: still the block somebody saved, and the page can be saved around it.
        $collection->forceDelete();
        $saved = $region->storeBlocks($tree);

        $this->assertSame('picks', $saved[0]['values']['collection']);
    }

    #[Test]
    public function the_block_stands_in_a_region_of_the_layout(): void
    {
        $this->installBlock();
        $collection = $this->collection(2);

        $region = Region::query()->create(['name' => 'footer']);
        $region->saveDraft(['blocks' => $region->storeBlocks([$this->node(['collection' => 'picks', 'view' => 'carousel'])])]);
        $region->publish();

        $html = $this->region();

        $this->assertStringContainsString('data-wx-block="product-collection"', $html);
        $this->assertStringContainsString('data-collection="picks"', $html);
        $this->assertStringContainsString('webx-catalog-collection--carousel', $html);

        $collection->delete();

        $this->assertStringNotContainsString('data-collection="picks"', $this->region());

        $collection->forceDelete();

        $this->assertStringNotContainsString('data-collection="picks"', $this->region());
    }

    #[Test]
    public function the_picker_finds_by_key_puts_the_exact_one_first_and_marks_what_is_off(): void
    {
        ProductCollection::query()->create(['title' => 'Wheels', 'key' => 'picks-wheels']);
        ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks', 'is_enabled' => false]);
        ProductCollection::query()->create(['title' => 'Other', 'key' => 'other']);
        ProductCollection::query()->create(['title' => 'Binned', 'key' => 'picks-binned'])->delete();

        $this->assertInstanceOf(CollectionTarget::class, $this->app->make(RelationTargets::class)->find(CollectionTarget::KEY));

        // No permission of the section: choosing one is not building one.
        $rows = $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/relations/'.CollectionTarget::KEY.'?q=picks')
            ->assertOk()
            ->json('data');

        $this->assertSame(['picks', 'picks-wheels'], array_column($rows, 'key'));
        $this->assertSame('Picks', $rows[0]['title']);
        $this->assertSame('picks', $rows[0]['subtitle']);
        $this->assertSame([false, true], array_column($rows, 'visible'));
    }

    #[Test]
    public function the_picker_finds_by_title_too(): void
    {
        ProductCollection::query()->create(['title' => ['en' => 'Winter tyres', 'ru' => 'Зимние шины'], 'key' => 'winter']);
        ProductCollection::query()->create(['title' => 'Other', 'key' => 'other']);

        $rows = (new CollectionTarget)->candidates('Зимние', 'ru');

        $this->assertSame(['winter'], array_column($rows, 'key'));
        $this->assertSame('Зимние шины', $rows[0]['title']);
    }

    #[Test]
    public function a_chosen_collection_in_the_bin_is_described_as_being_there(): void
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks']);
        $collection->delete();

        $rows = (new CollectionTarget)->describe([$collection->id], 'en');

        $this->assertSame([true], array_column($rows, 'trashed'));
        $this->assertSame([false], array_column($rows, 'visible'));
    }

    private function installBlock(): Block
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['catalog-collections']])->assertSuccessful();

        return Block::query()->where('slug', 'product-collection')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function collection(int $products, array $attributes = []): ProductCollection
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks', ...$attributes])->refresh();

        for ($position = 0; $position < $products; $position++) {
            $collection->products()->attach($this->product('Part '.$position)->id, ['position' => $position]);
        }

        // The list is written past the model here, and a path with no save moves the group itself.
        $collection->touch();

        return $collection;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{key: string, type: string, values: array<string, mixed>}
     */
    private function node(array $values): array
    {
        return ['key' => 'k1', 'type' => 'product-collection', 'values' => $values];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function render(array $values): string
    {
        $this->app->forgetScopedInstances();

        return (string) $this->app->make(Renderer::class)->render([$this->node($values)]);
    }

    private function region(): string
    {
        $this->app->forgetScopedInstances();

        return Blade::render('<x-webx-blocks::region name="footer" />');
    }

    private function editor(): CmsUser
    {
        return CmsUser::query()->create([
            'name' => 'Editor',
            'email' => 'editor@example.test',
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);
    }
}
