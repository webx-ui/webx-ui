<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLandings\Models\Landing;

/**
 * §8.4 of the landings spec: the API the section of the panel reads and writes, and its refusals.
 */
final class ApiTest extends TestCase
{
    private Category $laptops;

    private Brand $apple;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laptops = $this->category('laptops');
        $this->apple = $this->brand('Apple');
        $this->product('MacBook Air', $this->laptops, $this->apple, ['price' => 900]);
        $this->product('XPS', $this->laptops, null, ['price' => 1200]);
    }

    #[Test]
    public function a_landing_is_created_counted_and_read_back_with_its_set_as_chips(): void
    {
        $pick = $this->product('Studio Display', $this->laptops);

        $id = $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'category_id' => $this->laptops->id,
            'filters' => ['brand' => ['values' => [$this->apple->id]], 'price' => ['min' => 0, 'max' => '1000']],
            'name' => 'Cheap Apple laptops',
            'slug' => 'cheap-apple',
            'sort' => 'price_desc',
            'recommended' => [$pick->id],
            'seo' => ['title' => ['en' => 'Buy cheap Apple laptops']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.products_count', 1)
            ->assertJsonPath('data.filters.brand.values', [(string) $this->apple->id])
            ->assertJsonPath('data.filters.price', ['min' => 0, 'max' => 1000])
            ->assertJsonPath('data.chips.0.text', 'Apple')
            ->assertJsonPath('data.chips.1.text', '0 – 1000')
            ->assertJsonPath('data.recommended', [$pick->id])
            ->assertJsonPath('data.url', 'http://localhost/cheap-apple')
            ->json('data.id');

        $this->get('/cheap-apple')->assertNotFound();

        $this->actingAs($this->editor(), 'cms')->postJson($this->api("{$id}/publish"))->assertOk()->assertJsonPath('data.is_published', true);
        $this->get('/cheap-apple')->assertOk()->assertSee('<title>Buy cheap Apple laptops</title>', false);

        $this->actingAs($this->editor(['catalog.view']), 'cms')->getJson($this->api())->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($this->editor(['catalog.view']), 'cms')->postJson($this->api(), [])->assertForbidden();
    }

    #[Test]
    public function one_set_on_one_base_is_one_landing_and_the_refusal_names_it(): void
    {
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])], ['name' => 'Apple laptops']);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api(), [
            'category_id' => $this->laptops->id,
            'filters' => ['brand' => ['values' => [(string) $this->apple->id]]],
            'name' => 'Again',
            'slug' => 'again',
        ])->assertUnprocessable()->assertJsonPath('errors.filters.0', 'This set is already the landing “Apple laptops” on this base.');

        // The same set on the whole catalogue is another landing.
        $this->actingAs($editor, 'cms')->postJson($this->api(), [
            'filters' => ['brand' => ['values' => [$this->apple->id]]],
            'name' => 'Apple',
            'slug' => 'apple-everything',
        ])->assertCreated();

        // The form warns before the save refuses.
        $this->actingAs($editor, 'cms')->postJson($this->api('count'), [
            'category_id' => $this->laptops->id,
            'filters' => ['brand' => ['values' => [$this->apple->id]]],
        ])->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.taken.name', 'Apple laptops');
    }

    #[Test]
    public function the_category_in_the_set_an_unknown_facet_and_an_empty_set_are_refused(): void
    {
        $editor = $this->editor();
        $base = ['category_id' => $this->laptops->id, 'name' => 'X', 'slug' => 'x'];

        $this->actingAs($editor, 'cms')->postJson($this->api(), [...$base, 'filters' => ['category' => ['values' => [$this->laptops->id]]]])
            ->assertUnprocessable()->assertJsonPath('errors.filters.0', 'The category is the landing’s base, not a part of its set.');

        $this->actingAs($editor, 'cms')->postJson($this->api(), [...$base, 'filters' => ['nothing' => ['values' => [1]]]])
            ->assertUnprocessable()->assertJsonPath('errors.filters.0', 'There is no facet “nothing”.');

        $this->actingAs($editor, 'cms')->postJson($this->api(), [...$base, 'filters' => ['brand' => ['values' => []]]])
            ->assertUnprocessable()->assertJsonPath('errors.filters.0', 'Choose at least one value of a facet.');

        $this->assertSame(0, Landing::withTrashed()->count());
    }

    #[Test]
    public function a_landings_slug_and_a_categorys_never_meet_and_a_new_slug_leaves_a_301(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api(), [
            'category_id' => $this->laptops->id,
            'filters' => ['brand' => ['values' => [$this->apple->id]]],
            'name' => 'Apple laptops',
            'slug' => 'laptops',
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');

        $this->assertSame(0, Landing::withTrashed()->count());

        $landing = $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);

        $this->actingAs($editor, 'cms')->postJson('/api/cms/catalog/categories', ['values' => ['name' => 'Taken', 'slug' => 'laptops-apple']])
            ->assertUnprocessable();

        $this->actingAs($editor, 'cms')->putJson($this->api((string) $landing->id), ['slug' => 'apple-laptops'])->assertOk();

        $this->get('/laptops-apple')->assertStatus(301)->assertRedirect('/apple-laptops');
        $this->get('/apple-laptops')->assertOk();
    }

    #[Test]
    public function the_list_filters_by_base_attention_publication_and_emptiness(): void
    {
        $gaming = $this->category('gaming', $this->laptops);
        $dell = $this->brand('Dell');
        $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);
        $this->landing('gaming-dell', $gaming, ['brand' => $this->brands([$dell])], ['is_published' => false]);
        $this->landing('apple', null, ['brand' => $this->brands([$this->apple])], ['attention' => Landing::VALUE_REMOVED]);
        $this->artisan('webx:catalog-landings:count', ['--all' => true]);

        $editor = $this->editor(['catalog.view']);
        $slugs = fn (string $query): array => array_map(
            static fn (array $row): string => $row['slug']['en'],
            $this->actingAs($editor, 'cms')->getJson($this->api().$query)->assertOk()->json('data'),
        );

        $this->assertSame(['laptops-apple', 'gaming-dell'], $slugs('?category='.$this->laptops->id));
        $this->assertSame(['gaming-dell'], $slugs('?category='.$gaming->id));
        $this->assertSame(['apple'], $slugs('?category=root'));
        $this->assertSame(['apple'], $slugs('?attention=1'));
        $this->assertSame(['gaming-dell'], $slugs('?published=0'));
        $this->assertSame(['gaming-dell'], $slugs('?empty=1'));
        $this->assertSame(['laptops-apple'], $slugs('?q=laptops-a'));
    }

    #[Test]
    public function a_save_clears_the_mark_and_the_journal_holds_the_edit(): void
    {
        $landing = $this->landing('apple', null, ['brand' => $this->brands([$this->apple])], ['attention' => Landing::VALUE_REMOVED]);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api((string) $landing->id), ['name' => 'Apple everywhere'])
            ->assertOk()
            ->assertJsonPath('data.attention', null);

        $entry = HistoryEntry::query()->where('subject_type', Landing::TYPE)->where('event', HistoryEntry::UPDATED)->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertSame(['name.en', 'attention'], array_column((array) $entry->changes, 'field'));
    }

    #[Test]
    public function the_constructor_reads_the_facets_of_a_base_with_their_values(): void
    {
        $facets = $this->actingAs($this->editor(['catalog.view']), 'cms')
            ->getJson($this->api('facets?category='.$this->laptops->id))
            ->assertOk()
            ->json('data');

        $byKey = array_column($facets, null, 'key');

        $this->assertArrayNotHasKey('category', $byKey);
        $this->assertSame([['value' => (string) $this->apple->id, 'label' => 'Apple', 'count' => 1]], $byKey['brand']['values']);
        $this->assertSame('range', $byKey['price']['kind']);
        $this->assertEquals(900, $byKey['price']['min']);
    }

    #[Test]
    public function the_bin_and_back(): void
    {
        $landing = $this->landing('laptops-apple', $this->laptops, ['brand' => $this->brands([$this->apple])]);
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson($this->api((string) $landing->id))->assertNoContent();
        $this->get('/laptops-apple')->assertNotFound();
        $this->actingAs($editor, 'cms')->getJson($this->api('?trashed=1'))->assertOk()->assertJsonPath('data.0.id', $landing->id);

        $this->actingAs($editor, 'cms')->postJson($this->api("{$landing->id}/restore"))->assertOk();
        $this->get('/laptops-apple')->assertOk();
    }
}
