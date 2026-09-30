<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\PartSchema;
use WebxUi\Catalog\Parts\ProductPart;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogLabels\Catalog\LabelFacet;
use WebxUi\CatalogLabels\Models\Label;

/**
 * §2.1 and §9 of the dictionaries spec: several labels on a product, a filter that counts without
 * its own choice, service labels that are neither a choice nor a badge, the rule that a label on
 * products stays, and edits that mark the products in one statement.
 */
final class LabelsTest extends TestCase
{
    #[Test]
    public function a_product_carries_several_labels_and_the_form_writes_them_into_one_journal_row(): void
    {
        $sale = $this->label('Sale');
        $new = $this->label('New');
        $product = $this->product('Belt', $this->category('belts'));

        $values = $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['price' => 12, 'labels.ids' => [$new->id, $sale->id, $new->id]],
        ])->assertOk()->json('data.values');

        // A key with a dot is one key, which a JSON path would read as two (docs/pitfalls).
        $this->assertEqualsCanonicalizing([$sale->id, $new->id], $values['labels.ids']);
        $this->assertSame(2, DB::table(Label::LINKS)->where('product_id', $product->id)->count());

        $rows = HistoryEntry::query()->where('subject_type', Product::TYPE)->where('event', 'updated')->get();
        $this->assertCount(1, $rows);
        $changes = collect((array) $rows[0]->changes)->keyBy('field');
        $this->assertEqualsCanonicalizing(['price', 'labels.ids'], $changes->keys()->all());
        $this->assertSame(['Sale', 'New'], $changes['labels.ids']['to']);
    }

    #[Test]
    public function an_unknown_label_is_refused_under_its_field(): void
    {
        $product = $this->product('Belt', $this->category('belts'));

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['labels.ids' => [404]],
        ])->assertStatus(422)->assertJsonValidationErrors('labels.ids');
    }

    #[Test]
    public function a_part_that_fails_takes_the_labels_back_with_the_rest_of_the_save(): void
    {
        $sale = $this->label('Sale');
        $product = $this->product('Belt', $this->category('belts'));
        $this->withAFailingPart();

        try {
            $this->withoutExceptionHandling()->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
                'values' => ['price' => 99, 'labels.ids' => [$sale->id], 'boom.go' => 'now'],
            ]);
            $this->fail('The failing part let the save through.');
        } catch (RuntimeException $failure) {
            $this->assertSame('The part failed after the labels were written.', $failure->getMessage());
        }

        $this->assertSame(10.0, (float) $product->refresh()->price);
        $this->assertSame(0, DB::table(Label::LINKS)->count());
        $this->assertSame(0, HistoryEntry::query()->where('subject_type', Product::TYPE)->where('event', 'updated')->count());
    }

    #[Test]
    public function the_filter_counts_every_label_without_narrowing_by_its_own_choice(): void
    {
        $laptops = $this->category('laptops');
        $sale = $this->label('Sale');
        $new = $this->label('New');
        $this->product('One', $laptops, $sale, $new);
        $this->product('Two', $laptops, $sale);
        $this->product('Three', $laptops, $new);
        $this->product('Four', $laptops);

        $result = $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(
            locale: 'en',
            facets: [LabelFacet::KEY => FacetValue::of([(string) $sale->id])],
            count: [LabelFacet::KEY],
        ));

        $this->assertSame(2, $result->total);
        $this->assertSame([(string) $sale->id => 2, (string) $new->id => 2], $result->facet(LabelFacet::KEY)?->counts);

        // Two chosen is either of them.
        $both = $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(
            locale: 'en',
            facets: [LabelFacet::KEY => FacetValue::of([(string) $sale->id, (string) $new->id])],
        ));
        $this->assertSame(3, $both->total);
    }

    #[Test]
    public function a_service_label_is_neither_a_choice_of_the_filter_nor_a_badge(): void
    {
        $laptops = $this->category('laptops');
        $sale = $this->label('Sale', ['color' => 'danger']);
        $newsletter = $this->label('Newsletter', ['is_badge' => false, 'is_visible' => false]);
        $product = $this->product('One', $laptops, $sale, $newsletter);

        $result = $this->app->make(Catalog::class)->engine()->search(new CatalogQuery(locale: 'en', count: [LabelFacet::KEY]));
        $this->assertSame([(string) $sale->id => 1], $result->facet(LabelFacet::KEY)?->counts);

        $facet = new LabelFacet;
        $this->assertSame([], $facet->resolveSlugs(['newsletter'], 'en'));
        $this->assertSame(['sale' => (string) $sale->id], $facet->resolveSlugs(['sale', 'newsletter'], 'en'));

        $parts = $this->app->make(StorefrontParts::class);
        $parts->prepare(new Collection([$product]));
        $html = $parts->render('catalog.card.badges', ['product' => $product]);

        $this->assertStringContainsString('wx-catalog-badge--danger', $html);
        $this->assertStringContainsString('>Sale<', $html);
        $this->assertStringNotContainsString('Newsletter', $html);
    }

    #[Test]
    public function a_label_on_products_cannot_be_deleted_and_says_how_many(): void
    {
        $sale = $this->label('Sale');
        $laptops = $this->category('laptops');
        $this->product('One', $laptops, $sale);
        $this->product('Two', $laptops, $sale);

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api("labels/{$sale->id}"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'The label is on products. Products: 2');

        $this->assertFalse($sale->refresh()->trashed());

        DB::table(Label::LINKS)->delete();

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api("labels/{$sale->id}"))->assertNoContent();
    }

    #[Test]
    public function an_edit_of_a_label_marks_its_products_for_the_engine_in_one_statement(): void
    {
        $sale = $this->label('Sale');
        $laptops = $this->category('laptops');
        $one = $this->product('One', $laptops, $sale);
        $two = $this->product('Two', $laptops, $sale);
        $this->product('Three', $laptops);
        DB::table('catalog_index_queue')->delete();

        DB::enableQueryLog();
        $this->actingAs($this->editor(), 'cms')->putJson($this->api("labels/{$sale->id}"), [
            'values' => ['title' => ['en' => 'Big sale'], 'color' => 'danger'],
        ])->assertOk();
        $statements = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'catalog_index_queue'));
        DB::disableQueryLog();

        $this->assertCount(1, $statements);
        $this->assertEqualsCanonicalizing([$one->id, $two->id], DB::table('catalog_index_queue')->pluck('product_id')->map(intval(...))->all());

        // Nothing a product shows changed: nothing is marked.
        DB::table('catalog_index_queue')->delete();
        $sale->refresh()->update(['position' => 7]);
        $this->assertSame(0, DB::table('catalog_index_queue')->count());
    }

    #[Test]
    public function a_new_label_takes_its_code_from_the_name_and_a_taken_code_is_refused(): void
    {
        $editor = $this->editor();

        $first = $this->actingAs($editor, 'cms')->postJson($this->api('labels'), ['title' => ['en' => 'Big Sale']])->assertCreated();
        $second = $this->actingAs($editor, 'cms')->postJson($this->api('labels'), ['title' => ['en' => 'Big sale']])->assertCreated();

        $this->assertSame('big-sale', Label::query()->findOrFail($first->json('data.id'))->code);
        $this->assertSame('big-sale-2', Label::query()->findOrFail($second->json('data.id'))->code);

        $id = (int) $second->json('data.id');

        $this->actingAs($editor, 'cms')->putJson($this->api("labels/{$id}"), ['values' => ['code' => 'big-sale']])
            ->assertStatus(422)->assertJsonValidationErrors('code');
        $this->actingAs($editor, 'cms')->putJson($this->api("labels/{$id}"), ['values' => ['code' => 'Big_Sale']])
            ->assertStatus(422)->assertJsonValidationErrors('code');
        $this->actingAs($editor, 'cms')->putJson($this->api("labels/{$id}"), ['values' => ['color' => '#ff0000']])
            ->assertStatus(422)->assertJsonValidationErrors('color');

        $values = $this->actingAs($editor, 'cms')->getJson($this->api("labels/{$id}"))->assertOk()->json('data.values');
        $this->assertSame('big-sale-2', $values['code']);
        $this->assertSame('neutral', $values['color']);
        $this->assertTrue($values['is_badge']);
    }

    #[Test]
    public function the_panels_list_offers_labels_in_their_own_order_and_the_bulk_dialog_knows_where_to_ask(): void
    {
        $laptops = $this->category('laptops');
        $sale = $this->label('Sale');
        $top = $this->label('Top');
        DB::table('catalog_labels')->where('id', $top->id)->update(['position' => 1]);
        DB::table('catalog_labels')->where('id', $sale->id)->update(['position' => 2]);
        $this->product('One', $laptops, $sale);
        $this->product('Two', $laptops, $sale);
        $this->product('Three', $laptops, $top);

        $editor = $this->editor();
        $values = $this->actingAs($editor, 'cms')->getJson($this->api('products'))->assertOk()->json('facets.label.values');

        // By position — neither by count (Sale has two) nor by id (Sale is older).
        $this->assertSame([(string) $top->id, (string) $sale->id], array_column($values, 'value'));
        $this->assertSame([1, 2], array_column($values, 'count'));

        $described = $this->actingAs($editor, 'cms')->getJson($this->api('bulk'))->assertOk()->json('data');
        $this->assertIsArray($described);
        $actions = array_column($described, null, 'key');
        $this->assertSame('catalog/labels', $actions['add-label']['params'][0]['source']);
        $this->assertSame('catalog/labels', $actions['remove-label']['params'][0]['source']);
    }

    #[Test]
    public function the_list_is_read_with_the_catalogue_and_written_with_its_management(): void
    {
        $this->label('Sale');

        $this->actingAs($this->editor(['catalog.view']), 'cms')->getJson($this->api('labels'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.products_count', 0);
        $this->actingAs($this->editor(['catalog.view']), 'cms')->postJson($this->api('labels'), ['title' => 'New'])
            ->assertForbidden();
    }

    #[Test]
    public function a_template_picks_products_by_the_code_of_a_label(): void
    {
        $laptops = $this->category('laptops');
        $sale = $this->label('Sale');
        $newsletter = $this->label('Newsletter', ['is_visible' => false, 'is_badge' => false]);
        $one = $this->product('One', $laptops, $sale);
        $two = $this->product('Two', $laptops, $newsletter);
        $this->product('Three', $laptops);

        $ids = static fn ($query): array => $query->models()->modelKeys();

        $this->assertSame([$one->id], $ids(products()->{'label'}('sale')));
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $ids(products()->{'label'}('sale', 'newsletter')));
        $this->assertSame([], $ids(products()->{'label'}('nobody')));
        $this->assertCount(3, $ids(products()->{'label'}()));
    }

    #[Test]
    public function a_label_is_put_on_and_taken_off_many_products_at_once(): void
    {
        $laptops = $this->category('laptops');
        $sale = $this->label('Sale');
        $one = $this->product('One', $laptops, $sale);
        $two = $this->product('Two', $laptops);

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('bulk'), [
            'action' => 'add-label',
            'params' => ['label_id' => $sale->id],
            'selection' => ['ids' => [$one->id, $two->id]],
        ])->assertOk()->assertJsonPath('data.done', 2);

        $this->assertSame(2, DB::table(Label::LINKS)->where('label_id', $sale->id)->count());

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('bulk'), [
            'action' => 'remove-label',
            'params' => ['label_id' => $sale->id],
            'selection' => ['ids' => [$one->id, $two->id]],
        ])->assertOk();

        $this->assertSame(0, DB::table(Label::LINKS)->count());
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
                throw new RuntimeException('The part failed after the labels were written.');
            }
        });

        $this->app->make(ScreenRegistry::class)->extend(Product::SCREEN, [[
            'op' => 'add',
            'target' => 'main',
            'node' => ['id' => 'boom-go', 'type' => 'wx-input', 'name' => 'boom.go'],
        ]]);
    }
}
