<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Filter\BaseFacets;
use WebxUi\Catalog\Filter\FacetSet;
use WebxUi\Catalog\Tests\Fixtures\ColourFacet;

/**
 * The stored set and the facets a base offers it: a satellite knows only the core, so what the
 * landings' tests cover through their forms is held here without them.
 */
final class FacetSetTest extends TestCase
{
    private Facets $facets;

    protected function setUp(): void
    {
        parent::setUp();

        ColourFacet::migrate();
        $this->facets = $this->app->make(Facets::class);
        $this->facets->register(new ColourFacet);
    }

    #[Test]
    public function one_set_has_one_spelling_and_one_hash(): void
    {
        $one = FacetSet::from(['z' => ['values' => ['10', 2, ' 2 ', '']], 'a' => ['min' => '5', 'max' => null]]);
        $other = FacetSet::from(['a' => ['min' => 5.0], 'z' => ['values' => [2, '10']]]);

        $this->assertSame(['a' => ['min' => 5.0, 'max' => null], 'z' => ['values' => ['2', '10']]], $one->all());
        $this->assertTrue($one->equals($other));
        $this->assertSame($one->hash(7), $other->hash(7));
        $this->assertNotSame($one->hash(7), $one->hash(null));
    }

    #[Test]
    public function what_is_not_a_set_is_left_out(): void
    {
        $this->assertTrue(FacetSet::from('colour')->isEmpty());
        $this->assertTrue(FacetSet::from(['colour' => 'black', 'size' => ['values' => []], 'price' => []])->isEmpty());
        $this->assertNull(FacetSet::empty()->hash(null));
    }

    #[Test]
    public function a_merged_value_moves_and_a_deleted_one_drops_the_emptied_facet(): void
    {
        $set = FacetSet::from(['colour' => ['values' => ['black', 'white']]]);

        $this->assertSame(['blue', 'white'], $set->retarget('colour', 'black', 'blue')->all()['colour']['values']);
        $this->assertSame(['white'], $set->retarget('colour', 'black', null)->all()['colour']['values']);
        $this->assertFalse($set->retarget('colour', 'black', null)->retarget('colour', 'white', null)->has('colour'));
        $this->assertTrue($set->retarget('colour', 'red', null)->equals($set));
    }

    #[Test]
    public function a_facet_the_registry_lacks_stays_in_the_set_and_out_of_the_state(): void
    {
        $set = FacetSet::from([
            'colour' => ['values' => ['black']],
            'gone' => ['values' => ['1']],
            CategoryFacet::KEY => ['values' => ['5']],
        ]);

        $this->assertSame(3, $set->size());
        $this->assertSame(['colour'], array_keys($set->state($this->facets)->all()));
        $this->assertSame(['colour'], $set->known($this->facets)->keys());
    }

    #[Test]
    public function the_kind_of_the_facet_decides_how_a_choice_is_read(): void
    {
        $mixedUp = FacetSet::from([
            'colour' => ['min' => 1, 'max' => 2],
            'price' => ['values' => ['100']],
        ])->state($this->facets);

        $this->assertSame([], $mixedUp->all());

        $state = FacetSet::from([
            'colour' => ['values' => ['black']],
            'price' => ['min' => 100, 'max' => null],
        ])->state($this->facets);

        $this->assertSame(['black'], $state->all()['colour']->values);
        $this->assertSame(100.0, $state->all()['price']->min);
    }

    #[Test]
    public function a_base_offers_what_its_products_have_without_the_category_facet(): void
    {
        $laptops = $this->category('laptops');
        ColourFacet::paint($this->product('Black', $laptops, ['price' => 100]), 'black');
        ColourFacet::paint($this->product('Another black', $laptops, ['price' => 300]), 'black');

        $offered = $this->app->make(BaseFacets::class)->of(null, 'en');
        $keys = array_map(static fn (array $row): string => $row['facet']->key(), $offered);

        $this->assertNotContains(CategoryFacet::KEY, $keys);
        $this->assertContains('colour', $keys);

        $colour = $offered[array_search('colour', $keys, true)];
        $this->assertSame(2, $colour['counts']['black'] ?? null);
    }
}
