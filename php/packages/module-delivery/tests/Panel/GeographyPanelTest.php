<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Geography\Editor;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;

/**
 * The geography by hand on a clean site (decision 42): a country switched on, a region and a
 * locality made, the duplicate offered instead of a second town, and a locality in use switched
 * off rather than deleted.
 */
final class GeographyPanelTest extends PanelTestCase
{
    #[Test]
    public function on_a_clean_site_a_country_a_region_and_a_locality_are_made_by_hand(): void
    {
        $this->signIn();

        $this->getJson($this->api('geography/countries'))
            ->assertOk()
            ->assertJsonPath('data.countries', [])
            ->assertJsonFragment(['code' => 'DE']);

        $this->postJson($this->api('geography/countries'), ['code' => 'xx'])->assertCreated();
        $this->assertTrue(GeoCountry::query()->find('XX')?->active);

        $region = $this->postJson($this->api('geography/regions'), ['country' => 'XX', 'names' => ['en' => 'Region A']])
            ->assertCreated()
            ->json('data.id');

        $made = $this->postJson($this->api('geography/localities'), ['values' => [
            'names' => ['en' => 'Town A'],
            'kind' => 'town',
            'country' => 'XX',
            'region_id' => $region,
            'lat' => null,
            'lng' => null,
            'weight' => null,
            'active' => true,
        ]])->assertCreated();

        $made->assertJsonPath('data.locality.source', 'manual')
            ->assertJsonPath('data.values.weight', 50);

        $this->getJson($this->api('geography/localities/suggest?q=tow'))
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Town A, Region A');

        $this->getJson($this->api('geography/localities?country=XX&source=manual'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.region.name', 'Region A');
    }

    #[Test]
    public function a_locality_like_an_existing_one_is_offered_instead_and_made_only_when_forced(): void
    {
        $this->signIn();
        $town = $this->town('O’Town');

        $values = ['names' => ['en' => "O'town"], 'kind' => 'town', 'country' => 'XX', 'region_id' => $town->region_id, 'active' => true];

        $this->postJson($this->api('geography/localities'), ['values' => $values])
            ->assertStatus(409)
            ->assertJsonPath('similar.0.id', $town->id);

        $this->assertSame(1, GeoLocality::query()->count());

        $this->postJson($this->api('geography/localities'), ['values' => $values, 'force' => true])->assertCreated();
        $this->assertSame(2, GeoLocality::query()->count());
    }

    #[Test]
    public function a_locality_in_use_is_switched_off_and_a_free_one_deleted(): void
    {
        $this->signIn();
        $held = $this->town('Town A');
        $free = $this->town('Town B');
        $this->point($held);

        $this->getJson($this->api('geography/localities/'.$held->id))
            ->assertOk()
            ->assertJsonPath('data.locality.held_by', ['delivery points']);

        $this->deleteJson($this->api('geography/localities/'.$held->id))->assertOk()->assertJsonPath('data.removal', 'deactivated');
        $this->deleteJson($this->api('geography/localities/'.$free->id))->assertOk()->assertJsonPath('data.removal', 'deleted');

        $this->assertFalse($held->refresh()->active);
        $this->assertNull(GeoLocality::query()->find($free->id));

        $this->postJson($this->api('geography/localities/'.$held->id.'/activate'))->assertNoContent();
        $this->assertTrue($held->refresh()->active);

        // The row's «Switch off»: out of the suggestions, still there.
        $this->postJson($this->api('geography/localities/'.$held->id.'/deactivate'))->assertNoContent();
        $this->assertFalse($held->refresh()->active);
    }

    #[Test]
    public function the_regions_keep_the_order_they_are_dragged_to(): void
    {
        $this->signIn();
        $this->postJson($this->api('geography/countries'), ['code' => 'XX'])->assertCreated();

        $ids = [];

        foreach (['Region A', 'Region B', 'Region C'] as $name) {
            $ids[$name] = $this->postJson($this->api('geography/regions'), ['country' => 'XX', 'names' => ['en' => $name]])
                ->assertCreated()
                ->json('data.id');
        }

        // Made one after another, they come in that order before anyone drags.
        $this->assertSame(['Region A', 'Region B', 'Region C'], array_column($this->getJson($this->api('geography/regions?country=XX'))->json('data'), 'name'));

        $this->putJson($this->api('geography/regions/order'), [
            'country' => 'XX',
            'ids' => [$ids['Region C'], $ids['Region A'], $ids['Region B']],
        ])->assertNoContent();

        $this->assertSame(['Region C', 'Region A', 'Region B'], array_column($this->getJson($this->api('geography/regions?country=XX'))->json('data'), 'name'));

        // A region made after the drag goes last, not over the first.
        $this->postJson($this->api('geography/regions'), ['country' => 'XX', 'names' => ['en' => 'Region D']])->assertCreated();
        $this->assertSame('Region D', $this->getJson($this->api('geography/regions?country=XX'))->json('data.3.name'));

        // Ordering needs the right to manage.
        $this->signIn(['delivery.view']);
        $this->putJson($this->api('geography/regions/order'), ['country' => 'XX', 'ids' => [$ids['Region A']]])->assertForbidden();
    }

    #[Test]
    public function a_region_with_localities_is_not_deleted(): void
    {
        $this->signIn();
        $town = $this->town('Town A');

        $this->deleteJson($this->api('geography/regions/'.$town->region_id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('region');

        $this->getJson($this->api('geography/regions?country=XX'))->assertOk()->assertJsonPath('data.0.localities', 1);
    }

    #[Test]
    public function a_country_switched_off_keeps_its_localities(): void
    {
        $this->signIn();
        $town = $this->town('Town A');

        $this->deleteJson($this->api('geography/countries/XX'))->assertNoContent();

        $this->assertFalse(GeoCountry::query()->find('XX')?->active);
        $this->assertNotNull(GeoLocality::query()->find($town->id));
    }

    #[Test]
    public function a_country_with_nothing_in_it_is_removed_and_one_in_use_only_switched_off(): void
    {
        $this->signIn();
        $this->town('Town A');
        $this->postJson($this->api('geography/countries'), ['code' => 'ZZ'])->assertCreated();

        $this->postJson($this->api('geography/countries/ZZ/remove'))->assertOk()->assertJsonPath('data.removal', 'deleted');
        $this->assertNull(GeoCountry::query()->find('ZZ'));

        $this->postJson($this->api('geography/countries/XX/remove'))->assertOk()->assertJsonPath('data.removal', 'deactivated');
        $this->assertFalse(GeoCountry::query()->find('XX')?->active);
    }

    #[Test]
    public function the_list_says_types_and_carriers_in_words(): void
    {
        $this->signIn();
        $this->app->make(Carriers::class)->register(new FakeCarrier);
        $town = $this->town('Town A');
        DeliveryCarrierLocality::query()->create([
            'carrier' => FakeCarrier::KEY,
            'external_id' => 'L1',
            'locality_id' => $town->id,
            'status' => 'matched',
            'record' => ['name' => 'Town A'],
        ]);

        $this->getJson($this->api('geography/localities?country=XX'))->assertOk()
            ->assertJsonPath('data.0.kind_label', 'Town')
            ->assertJsonPath('data.0.bindings.0.carrier_title', 'Carrier A');

        $this->getJson($this->api('geography/countries'))->assertOk()
            ->assertJsonPath('data.carriers', [['value' => FakeCarrier::KEY, 'label' => 'Carrier A']]);
    }

    /**
     * The lab's case: a demo country first, then a carrier's country switched on — the list with
     * no country names the towns of both, namesakes told apart by their districts; a search in the
     * wrong country is «nothing» that says where the towns are.
     */
    #[Test]
    public function the_list_without_a_country_shows_the_leading_carriers_towns_of_every_country_on(): void
    {
        $this->signIn();
        $this->town('Town A');
        $editor = $this->app->make(Editor::class);
        $region = $editor->createRegion('XY', ['en' => 'Region Y']);

        foreach (['District 1', 'District 2', 'District 3'] as $district) {
            $town = $editor->createLocality(['country' => 'XY', 'region_id' => $region->id, 'kind' => 'village', 'names' => ['en' => 'Happy Village'], 'district' => ['en' => $district]], force: true);
            $town->forceFill(['created_by' => 'carrier:'.FakeCarrier::KEY])->save();
        }

        $this->getJson($this->api('geography/localities'))->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('elsewhere', []);

        $found = $this->getJson($this->api('geography/localities?q=happy'))->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.country', 'XY')
            ->assertJsonPath('data.0.source', 'carrier');
        $this->assertEqualsCanonicalizing(['District 1', 'District 2', 'District 3'], array_column($found->json('data'), 'district'));

        // The demo country kept in the address: nothing there, and the answer says where.
        $this->getJson($this->api('geography/localities?country=XX&q=happy'))->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('elsewhere.0.code', 'XY')
            ->assertJsonPath('elsewhere.0.active', true)
            ->assertJsonCount(1, 'elsewhere');

        // Switched off, the country is out of the list with no country — and named as off.
        $editor->disableCountry('XY');

        $this->getJson($this->api('geography/localities?q=happy'))->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('elsewhere.0.code', 'XY')
            ->assertJsonPath('elsewhere.0.active', false);

        $this->getJson($this->api('geography/localities?country=XY&q=happy'))->assertOk()->assertJsonPath('total', 3);
        $this->getJson($this->api('geography/localities?q=nowhere'))->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('elsewhere', []);
    }

    #[Test]
    public function a_city_that_is_its_own_region_is_named_once(): void
    {
        $editor = $this->app->make(Editor::class);
        $region = $editor->createRegion('XX', ['en' => 'Capital']);
        $city = $editor->createLocality(['country' => 'XX', 'region_id' => $region->id, 'kind' => 'city', 'names' => ['en' => 'Capital']]);
        $town = $this->town('Town A');

        $this->assertSame('Capital', $city->load('region')->label('en'));
        $this->assertSame('Town A, Region A', $town->load('region')->label('en'));
    }
}
