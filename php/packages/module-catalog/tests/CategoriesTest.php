<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;

/**
 * §6: the tree, a slug unique across the site, hiding a branch, deleting only what is empty.
 */
final class CategoriesTest extends TestCase
{
    #[Test]
    public function a_taken_slug_is_a_422_naming_who_holds_it(): void
    {
        $this->category('laptops');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('categories'), [
            'values' => ['name' => ['en' => 'Notebooks'], 'slug' => ['en' => 'laptops']],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('slug');
        $this->assertStringContainsString('Laptops', (string) $response->json('errors.slug.0'));
        $this->assertSame(1, Category::query()->count());
    }

    #[Test]
    public function an_underscore_in_a_slug_is_refused_because_it_marks_a_filter(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api('categories'), [
            'values' => ['name' => ['en' => 'Gaming'], 'slug' => ['en' => 'gaming_laptops']],
        ])->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    #[Test]
    public function a_new_category_takes_its_slug_from_the_name_and_stands_under_its_parent(): void
    {
        $laptops = $this->category('laptops');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('categories'), [
            'values' => ['name' => ['en' => 'Gaming Laptops'], 'is_published' => true],
            'parent_id' => $laptops->id,
        ])->assertCreated();

        $gaming = Category::query()->findOrFail($response->json('data.category.id'));

        $this->assertSame('gaming-laptops', $gaming->getTranslation('slug', 'en'));
        $this->assertSame($laptops->id, $gaming->parent_id);
        // Flat: the address does not repeat the tree (decision 23).
        $this->get('/gaming-laptops')->assertOk();
    }

    #[Test]
    public function an_unpublished_category_hides_its_branch_but_not_a_product_with_another_way_in(): void
    {
        $laptops = $this->category('laptops');
        $gaming = $this->category('gaming', parent: $laptops);
        $sale = $this->category('sale');

        $only = $this->product('Only gaming', $gaming);
        $also = $this->product('Also on sale', $gaming);
        $also->categories()->attach($sale->id);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("categories/{$laptops->id}"), [
            'values' => ['is_published' => false],
        ])->assertOk();

        $this->assertFalse($gaming->refresh()->isVisible());
        $this->assertFalse($only->isVisible());
        $this->assertTrue($also->isVisible());

        $this->get('/gaming')->assertNotFound();
        $this->get("/only-gaming-{$only->id}")->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get("/also-on-sale-{$also->id}")->assertOk()->assertHeaderMissing('X-Robots-Tag');

        $this->assertSame(
            [$also->id],
            Product::query()->visible()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );

        $this->assertSame(1, HistoryEntry::query()->where('subject_type', 'catalog.category')->where('event', 'unpublished')->count());
    }

    #[Test]
    public function a_category_with_products_or_subcategories_is_not_deleted(): void
    {
        $laptops = $this->category('laptops');
        $this->category('gaming', parent: $laptops);
        $sale = $this->category('sale');
        $product = $this->product('ThinkPad', $this->category('other'));
        $product->categories()->attach($sale->id);

        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson($this->api("categories/{$laptops->id}"))
            ->assertStatus(422)
            ->assertJsonPath('meta.children', 1)
            ->assertJsonPath('meta.products', 0);

        // An additional category holds its products as firmly as a main one.
        $this->actingAs($editor, 'cms')->deleteJson($this->api("categories/{$sale->id}"))
            ->assertStatus(422)
            ->assertJsonPath('meta.products', 1);

        // A deleted product no longer holds anything.
        $product->delete();
        $this->actingAs($editor, 'cms')->deleteJson($this->api("categories/{$sale->id}"))->assertNoContent();

        $this->assertSoftDeleted($sale);
        $this->get('/sale')->assertStatus(410);

        $this->actingAs($editor, 'cms')->getJson($this->api('deleted?type=categories'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $sale->id);

        $this->actingAs($editor, 'cms')->postJson($this->api("categories/{$sale->id}/restore"))->assertOk();
        $this->get('/sale')->assertOk();
    }

    #[Test]
    public function the_tree_counts_each_product_once_with_everything_below(): void
    {
        $laptops = $this->category('laptops');
        $gaming = $this->category('gaming', parent: $laptops);
        $office = $this->category('office', parent: $laptops);

        $this->product('One', $gaming);
        $two = $this->product('Two', $gaming);
        $two->categories()->attach($office->id);
        $this->product('Three', $office);
        $this->product('Gone', $office)->delete();

        $tree = $this->actingAs($this->editor(['catalog.view']), 'cms')->getJson($this->api('categories'))
            ->assertOk()
            ->json('data');

        $this->assertSame('laptops', $tree[0]['slug']);
        $this->assertSame(3, $tree[0]['products_count']);
        $this->assertSame(['gaming', 'office'], array_column($tree[0]['children'], 'slug'));
        $this->assertSame([2, 2], array_column($tree[0]['children'], 'products_count'));
        $this->assertSame('http://localhost/gaming', $tree[0]['children'][0]['url']);
    }

    #[Test]
    public function a_move_changes_the_trail_and_not_the_address(): void
    {
        $laptops = $this->category('laptops');
        $tablets = $this->category('tablets');
        $gaming = $this->category('gaming', parent: $laptops);

        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api("categories/{$gaming->id}/move"), [
            'parent_id' => $tablets->id,
        ])->assertOk();

        $gaming->refresh();

        $this->assertSame($tablets->id, $gaming->parent_id);
        $this->assertSame('gaming', $gaming->routePath());

        $entry = HistoryEntry::query()->where('subject_type', 'catalog.category')->where('subject_id', $gaming->id)->where('event', 'updated')->firstOrFail();
        $this->assertSame([['field' => 'parent', 'from' => 'Laptops', 'to' => 'Tablets']], $entry->changes);

        $this->actingAs($editor, 'cms')->postJson($this->api("categories/{$tablets->id}/move"), [
            'parent_id' => $gaming->id,
        ])->assertStatus(422)->assertJsonValidationErrors('parent_id');

        // Before a sibling, at the top level.
        $this->actingAs($editor, 'cms')->postJson($this->api("categories/{$tablets->id}/move"), [
            'parent_id' => null,
            'before_id' => $laptops->id,
        ])->assertOk()->assertJsonPath('data.0.slug', 'tablets');
    }
}
