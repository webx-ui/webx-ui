<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartSchema;
use WebxUi\Catalog\Parts\ProductPart;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogBrands\Catalog\BrandFacet;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * §2.3 and §9 of the dictionaries spec: a brand's page is the catalogue narrowed to it, with the
 * filter's tail behind it and its own facet off; hidden is a 404 and out of the filter, deleted is
 * a 410, a taken slug is a 422; a brand products are of stays; an edit marks them in one
 * statement; the product form writes the brand in the save's one transaction and journal row.
 */
final class BrandsTest extends TestCase
{
    #[Test]
    public function a_brand_page_is_the_catalogue_narrowed_to_it_with_the_filter_tail_behind(): void
    {
        $laptops = $this->category('laptops');
        $phones = $this->category('phones');
        $apple = $this->brand('Apple', ['description' => '<p>Think different.</p>']);
        $dell = $this->brand('Dell');
        $this->product('MacBook', $apple, $laptops);
        $this->product('iPhone', $apple, $phones);
        $this->product('XPS', $dell, $laptops);

        $page = (string) $this->get('/brands/apple')->assertOk()
            ->assertSee('MacBook')->assertSee('iPhone')->assertDontSee('XPS')
            ->assertSee('<h1>Apple</h1>', false)
            ->assertSee('Think different.')
            ->getContent();

        // Its own facet is not on its page: the first level is the categories.
        $this->assertStringNotContainsString('/brand_', $page);
        $this->assertStringContainsString('/brands/apple/category_laptops', $page);

        $this->get('/brands/apple/category_laptops')->assertOk()
            ->assertSee('MacBook')->assertDontSee('iPhone')->assertDontSee('XPS')
            ->assertSee('Apple Laptops')
            ->assertDontSee('noindex', false)
            ->assertDontSee('Think different.');

        // A tail that is not the filter's is not a page of the brand; a facet of its own is not
        // a choice there either.
        $this->get('/brands/apple/whatever')->assertNotFound();
        $this->get('/brands/apple/brand_dell')->assertNotFound();

        $this->get('/brands')->assertOk()->assertSee('Apple')->assertSee('Dell')
            ->assertSee('href="http://localhost/brands/apple"', false);
    }

    #[Test]
    public function the_brand_is_an_indexable_facet_of_the_catalogue_named_by_its_slug(): void
    {
        $laptops = $this->category('laptops');
        $apple = $this->brand('Apple');
        $dell = $this->brand('Dell');
        $this->product('MacBook', $apple, $laptops);
        $this->product('XPS', $dell, $laptops);
        $this->product('Nameless', null, $laptops);

        $result = $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(locale: 'en', count: [BrandFacet::KEY]));
        $this->assertSame([(string) $apple->id => 1, (string) $dell->id => 1], $result->facet(BrandFacet::KEY)?->counts);

        $this->get('/laptops')->assertOk()->assertSee('/laptops/brand_apple', false);
        $this->get('/laptops/brand_apple')->assertOk()
            ->assertSee('MacBook')->assertDontSee('XPS')->assertDontSee('Nameless')
            ->assertSee('Laptops Apple')
            ->assertDontSee('noindex', false);
    }

    #[Test]
    public function a_renamed_slug_keeps_its_old_filter_address_as_a_301_to_the_new_one(): void
    {
        $laptops = $this->category('laptops');
        $apple = $this->brand('Apple');
        $this->product('MacBook', $apple, $laptops);

        $apple->update(['slug' => 'apple-inc']);
        $apple->refresh()->update(['slug' => 'apple-computers']);
        $this->get('/laptops/brand_apple')->assertStatus(301)->assertRedirect('http://localhost/laptops/brand_apple-computers');
        $this->get('/laptops/brand_apple-inc')->assertStatus(301)->assertRedirect('http://localhost/laptops/brand_apple-computers');
        $this->get('/laptops/brand_apple-computers')->assertOk()->assertSee('MacBook');
        $this->get('/laptops/brand_banana')->assertNotFound();
    }

    #[Test]
    public function a_hidden_brand_answers_404_and_is_neither_a_choice_of_the_filter_nor_on_a_card(): void
    {
        $laptops = $this->category('laptops');
        $apple = $this->brand('Apple');
        $dell = $this->brand('Dell', ['is_visible' => false]);
        $macbook = $this->product('MacBook', $apple, $laptops);
        $xps = $this->product('XPS', $dell, $laptops);

        $this->get('/brands/dell')->assertNotFound();
        $this->get('/brands/dell/category_laptops')->assertNotFound();
        $this->get('/laptops/brand_dell')->assertNotFound();
        $this->get('/brands')->assertOk()->assertSee('Apple')->assertDontSee('Dell');

        $result = $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(locale: 'en', count: [BrandFacet::KEY]));
        $this->assertSame([(string) $apple->id => 1], $result->facet(BrandFacet::KEY)?->counts);

        // The product keeps its brand; the site does not show it.
        $this->assertSame(1, DB::table(Brand::LINKS)->where('product_id', $xps->id)->count());

        $parts = $this->app->make(StorefrontParts::class);
        $parts->prepare(new Collection([$macbook, $xps]));

        $this->assertStringContainsString('href="http://localhost/brands/apple"', $parts->render('catalog.card.meta', ['product' => $macbook]));
        $this->assertStringNotContainsString('Dell', $parts->render('catalog.card.meta', ['product' => $xps]));
        $this->assertStringContainsString('wx-catalog-brand--product', $parts->render('catalog.product.aside', ['product' => $macbook]));
    }

    #[Test]
    public function a_brand_with_products_cannot_be_deleted_and_a_deleted_one_answers_410(): void
    {
        $apple = $this->brand('Apple');
        $this->product('MacBook', $apple);
        $this->product('iMac', $apple);

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api("brands/{$apple->id}"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Products are of this brand. Products: 2');

        $this->assertFalse($apple->refresh()->trashed());

        DB::table(Brand::LINKS)->delete();

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api("brands/{$apple->id}"))->assertNoContent();

        $this->get('/brands/apple')->assertStatus(410);
        $this->get('/brands/apple/category_laptops')->assertStatus(410);
        $this->get('/brands/nobody')->assertNotFound();
    }

    #[Test]
    public function a_taken_slug_is_refused_under_the_field_with_whoever_holds_it(): void
    {
        $this->brand('Apple');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api('brands'), ['title' => ['en' => 'Apple Inc.'], 'slug' => ['en' => 'apple']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->assertSame(1, Brand::query()->count());

        $created = $this->actingAs($editor, 'cms')->postJson($this->api('brands'), ['title' => ['en' => 'Lenovo']])->assertCreated();
        $id = (int) $created->json('data.id');

        $this->assertSame('lenovo', Brand::query()->findOrFail($id)->getTranslation('slug', 'en'));
        $this->get('/brands/lenovo')->assertOk();

        $this->actingAs($editor, 'cms')->putJson($this->api("brands/{$id}"), ['values' => ['slug' => ['en' => 'apple']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
        $this->assertSame('lenovo', Brand::query()->findOrFail($id)->getTranslation('slug', 'en'));

        // `_` marks the filter in an address, so no slug may hold it.
        $this->actingAs($editor, 'cms')->putJson($this->api("brands/{$id}"), ['values' => ['slug' => ['en' => 'len_ovo']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    #[Test]
    public function the_form_of_a_brand_reads_and_writes_its_own_fields_and_journals_them(): void
    {
        $apple = $this->brand('Apple');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api("brands/{$apple->id}"), ['values' => [
            'title' => ['en' => 'Apple Inc.'],
            'is_featured' => true,
            'description' => ['en' => '<p>Computers.</p>'],
            'seo' => ['title' => ['en' => 'Apple computers']],
        ]])->assertOk();

        $values = $this->actingAs($editor, 'cms')->getJson($this->api("brands/{$apple->id}"))->assertOk()->json('data.values');
        $this->assertSame(['en' => 'Apple Inc.'], $values['title']);
        $this->assertTrue($values['is_featured']);
        $this->assertSame(['en' => '<p>Computers.</p>'], $values['description']);
        $this->assertNull($values['logo']);

        $row = HistoryEntry::query()->where('subject_type', Brand::TYPE)->where('event', 'updated')->firstOrFail();
        $this->assertEqualsCanonicalizing(['title.en', 'description.en', 'is_featured'], collect((array) $row->changes)->pluck('field')->all());

        $this->get('/brands/apple')->assertOk()->assertSee('<title>Apple computers</title>', false);
    }

    #[Test]
    public function an_edit_of_a_brand_marks_its_products_for_the_engine_in_one_statement(): void
    {
        $apple = $this->brand('Apple');
        $one = $this->product('MacBook', $apple);
        $two = $this->product('iMac', $apple);
        $this->product('XPS', $this->brand('Dell'));
        DB::table('catalog_index_queue')->delete();

        DB::enableQueryLog();
        $this->actingAs($this->editor(), 'cms')->putJson($this->api("brands/{$apple->id}"), [
            'values' => ['title' => ['en' => 'Apple Inc.'], 'is_visible' => false],
        ])->assertOk();
        $statements = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'catalog_index_queue'));
        DB::disableQueryLog();

        $this->assertCount(1, $statements);
        $this->assertEqualsCanonicalizing([$one->id, $two->id], DB::table('catalog_index_queue')->pluck('product_id')->map(intval(...))->all());

        // Nothing a product shows changed: nothing is marked.
        DB::table('catalog_index_queue')->delete();
        $apple->refresh()->update(['position' => 7, 'is_featured' => true]);
        $this->assertSame(0, DB::table('catalog_index_queue')->count());
    }

    #[Test]
    public function the_product_form_writes_the_brand_into_the_save_and_its_one_journal_row(): void
    {
        $apple = $this->brand('Apple');
        $product = $this->product('MacBook');
        $editor = $this->editor();

        $values = $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['price' => 12, 'brand.id' => $apple->id],
        ])->assertOk()->json('data.values');

        // A key with a dot is one key, which a JSON path would read as two (docs/pitfalls).
        $this->assertSame($apple->id, $values['brand.id']);

        $rows = HistoryEntry::query()->where('subject_type', Product::TYPE)->where('event', 'updated')->get();
        $this->assertCount(1, $rows);
        $changes = collect((array) $rows[0]->changes)->keyBy('field');
        $this->assertEqualsCanonicalizing(['price', 'brand.id'], $changes->keys()->all());
        $this->assertSame('Apple', $changes['brand.id']['to']);

        $list = $this->actingAs($editor, 'cms')->getJson($this->api('products'))->assertOk();
        $this->assertSame('Apple', $list->json('data.0.columns.brand.name'));

        // Empty is no brand.
        $values = $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['brand.id' => null],
        ])->assertOk()->json('data.values');
        $this->assertNull($values['brand.id']);
        $this->assertSame(0, DB::table(Brand::LINKS)->count());

        $this->actingAs($editor, 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['brand.id' => 404],
        ])->assertStatus(422)->assertJsonValidationErrors(['brand.id' => 'There is no such brand.']);
    }

    #[Test]
    public function the_agent_is_not_told_about_a_main_brand_among_several(): void
    {
        // A product has one brand: the category sets' "the first is the main one" would send an
        // agent looking for the others.
        $description = $this->app->make(ToolRegistry::class)->tool('catalog_brands_list')->tool->description;

        $this->assertStringContainsString('the address it answers at', $description);
        $this->assertStringNotContainsString('main one', $description);
    }

    #[Test]
    public function a_part_that_fails_takes_the_brand_back_with_the_rest_of_the_save(): void
    {
        $apple = $this->brand('Apple');
        $product = $this->product('MacBook');
        $this->withAFailingPart();

        try {
            $this->withoutExceptionHandling()->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
                'values' => ['price' => 99, 'brand.id' => $apple->id, 'boom.go' => 'now'],
            ]);
            $this->fail('The failing part let the save through.');
        } catch (RuntimeException $failure) {
            $this->assertSame('The part failed after the brand was written.', $failure->getMessage());
        }

        $this->assertSame(10.0, (float) $product->refresh()->price);
        $this->assertSame(0, DB::table(Brand::LINKS)->count());
        $this->assertSame(0, HistoryEntry::query()->where('subject_type', Product::TYPE)->where('event', 'updated')->count());
    }

    #[Test]
    public function the_brand_is_set_and_taken_off_many_products_at_once(): void
    {
        $apple = $this->brand('Apple');
        $one = $this->product('MacBook', $this->brand('Dell'));
        $two = $this->product('iMac');

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('bulk'), [
            'action' => 'set-brand',
            'params' => ['brand_id' => $apple->id],
            'selection' => ['ids' => [$one->id, $two->id]],
        ])->assertOk()->assertJsonPath('data.done', 2);

        $this->assertSame(2, DB::table(Brand::LINKS)->where('brand_id', $apple->id)->count());

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('bulk'), [
            'action' => 'set-brand',
            'params' => ['brand_id' => null],
            'selection' => ['ids' => [$one->id]],
        ])->assertOk();

        $this->assertSame([$two->id], DB::table(Brand::LINKS)->pluck('product_id')->map(intval(...))->all());
    }

    #[Test]
    public function a_template_picks_products_by_brand_and_the_featured_brands(): void
    {
        $apple = $this->brand('Apple', ['is_featured' => true]);
        $dell = $this->brand('Dell');
        $hidden = $this->brand('Hidden', ['is_visible' => false, 'is_featured' => true]);
        $this->brand('Lenovo', ['is_featured' => true, 'position' => 0]);
        $one = $this->product('MacBook', $apple);
        $two = $this->product('XPS', $dell);
        $this->product('Ghost', $hidden);
        $this->product('Nameless');

        $ids = static fn ($query): array => $query->models()->modelKeys();

        $this->assertSame([$one->id], $ids(products()->{'brand'}('apple')));
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $ids(products()->{'brand'}('apple', (string) $dell->id)));
        $this->assertSame([], $ids(products()->{'brand'}('hidden')));
        $this->assertSame([], $ids(products()->{'brand'}('nobody')));
        $this->assertCount(4, $ids(products()->{'brand'}()));

        $this->assertSame(['Lenovo', 'Apple'], array_column(brands()->featured()->get(), 'name'));
        $this->assertSame(['Lenovo', 'Apple', 'Dell'], array_column(brands()->get(), 'name'));
        $this->assertSame('http://localhost/brands/apple', brands()->only([$apple->id])->first()['url'] ?? null);
    }

    #[Test]
    public function the_list_is_read_with_the_catalogue_and_written_with_its_management(): void
    {
        $this->brand('Apple');

        $this->actingAs($this->editor(['catalog.view']), 'cms')->getJson($this->api('brands'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.products_count', 0);
        $this->actingAs($this->editor(['catalog.view']), 'cms')->postJson($this->api('brands'), ['title' => 'New'])
            ->assertForbidden();
    }

    private function withAFailingPart(): void
    {
        $this->app->make(ProductParts::class)->register(new class implements ProductPart
        {
            public function key(): string
            {
                return 'boom';
            }

            public function describe(): PartSchema
            {
                return new PartSchema('Boom', 'boom', []);
            }

            public function rules(): array
            {
                return [];
            }

            public function read(Collection $products): array
            {
                return [];
            }

            public function write(Product $product, array $input): array
            {
                throw new RuntimeException('The part failed after the brand was written.');
            }
        });

        $this->app->make(ScreenRegistry::class)->extend(Product::SCREEN, [[
            'op' => 'add',
            'target' => 'main',
            'node' => ['id' => 'boom-go', 'type' => 'wx-input', 'name' => 'boom.go'],
        ]]);
    }
}
