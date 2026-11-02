<?php

declare(strict_types=1);

namespace WebxUi\Geography\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Geography\DuplicateLocality;
use WebxUi\Geography\Editor;
use WebxUi\Geography\GeographyException;
use WebxUi\Geography\LocalityReferences;
use WebxUi\Geography\LocalitySearch;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Geography\Removal;

final class EditorTest extends TestCase
{
    private function editor(): Editor
    {
        return $this->app->make(Editor::class);
    }

    public function test_a_country_is_turned_on_by_its_code_and_named_by_intl(): void
    {
        $country = $this->editor()->enableCountry('de');

        $this->assertSame('DE', $country->code);
        $this->assertTrue($country->active);
        $this->assertSame('Germany', $country->name('en'));
        $this->assertSame('Deutschland', $country->name('de'));

        $this->assertFalse($this->editor()->disableCountry('DE')->active);
    }

    public function test_a_private_use_code_is_a_country_named_by_the_site(): void
    {
        $country = $this->editor()->enableCountry('XX', names: ['en' => 'Demo land']);

        $this->assertSame('Demo land', $country->name('de'));
        $this->assertSame('XZ', GeoCountry::query()->create(['code' => 'XZ'])->name('en'));
    }

    public function test_an_unknown_country_code_is_refused(): void
    {
        $this->expectException(GeographyException::class);

        $this->editor()->enableCountry('ZY');
    }

    public function test_a_locality_made_by_hand_is_manual_and_weighed_by_its_kind(): void
    {
        $region = $this->editor()->createRegion('XX', ['en' => 'Region A']);

        $locality = $this->editor()->createLocality([
            'country' => 'xx',
            'names' => ['en' => ' Town  A ', 'de' => 'Stadt A'],
            'kind' => 'city',
            'region_id' => $region->id,
            'lat' => '10.1234567',
            'lng' => -20.5,
        ]);

        $this->assertSame('manual', $locality->created_by);
        $this->assertSame('town a', $locality->name_key);
        $this->assertSame(['en' => 'Town A', 'de' => 'Stadt A'], $locality->names);
        $this->assertSame(100, $locality->weight);
        $this->assertSame(10.123457, $locality->refresh()->lat);
        $this->assertTrue(GeoCountry::query()->findOrFail('XX')->active);
        $this->assertTrue($locality->isManual('names'));
        $this->assertSame(['code', 'names'], $region->manual_fields);
    }

    public function test_a_weight_set_by_hand_is_marked_even_on_a_hand_made_locality(): void
    {
        $region = $this->editor()->createRegion('XX', ['en' => 'Region A']);
        $plain = $this->editor()->createLocality(['country' => 'XX', 'names' => ['en' => 'Town A'], 'kind' => 'town', 'region_id' => $region->id]);
        $weighed = $this->editor()->createLocality(['country' => 'XX', 'names' => ['en' => 'Town B'], 'kind' => 'town', 'region_id' => $region->id, 'weight' => 7]);

        // The recount after a carrier's points reads the mark, not the whole record (§4.6).
        $this->assertNull($plain->manual_fields);
        $this->assertSame(['weight'], $weighed->refresh()->manual_fields);
        $this->assertSame(7, $weighed->weight);

        $this->editor()->updateLocality($plain, ['weight' => 30]);
        $this->assertSame(['weight'], $plain->refresh()->manual_fields);
    }

    public function test_a_similar_locality_is_found_before_a_second_one_is_made(): void
    {
        $region = $this->region('Region A');
        $existing = $this->locality('O’Town', ['region_id' => $region->id]);

        try {
            $this->editor()->createLocality([
                'country' => 'XX', 'names' => ['en' => "O'town"], 'kind' => 'town', 'region_id' => $region->id,
            ]);
            $this->fail('A duplicate was made.');
        } catch (DuplicateLocality $duplicate) {
            $this->assertSame([$existing->id], $duplicate->similar->pluck('id')->all());
        }

        $forced = $this->editor()->createLocality([
            'country' => 'XX', 'names' => ['en' => "O'town"], 'kind' => 'town', 'region_id' => $region->id,
        ], force: true);

        $this->assertNotSame($existing->id, $forced->id);
    }

    public function test_an_alias_or_another_language_counts_as_similar(): void
    {
        $locality = $this->locality('Town B', ['names' => ['en' => 'Town B', 'de' => 'Stadt B']]);

        $similar = $this->editor()->similar(['country' => 'XX', 'names' => ['en' => 'stadt b'], 'kind' => 'town']);

        $this->assertSame([$locality->id], $similar->pluck('id')->all());
    }

    public function test_another_region_or_kind_is_not_a_duplicate(): void
    {
        $regionA = $this->region('Region A');
        $regionB = $this->region('Region B');
        $this->locality('Town C', ['region_id' => $regionA->id]);

        $this->assertTrue($this->editor()->similar(['country' => 'XX', 'names' => ['en' => 'Town C'], 'kind' => 'town', 'region_id' => $regionB->id])->isEmpty());
        $this->assertTrue($this->editor()->similar(['country' => 'XX', 'names' => ['en' => 'Town C'], 'kind' => 'village', 'region_id' => $regionA->id])->isEmpty());
        $this->assertTrue($this->editor()->similar(['country' => 'XX', 'names' => ['en' => 'Town C'], 'kind' => 'town'])->isEmpty());
    }

    public function test_bad_locality_data_is_refused_with_a_reason(): void
    {
        $regionElsewhere = $this->region('Region Z', 'XY');

        foreach ([
            ['names' => ['en' => '  ']],
            ['kind' => 'metropolis'],
            ['region_id' => $regionElsewhere->id],
            ['lat' => 91],
            ['weight' => -1],
        ] as $bad) {
            try {
                $this->editor()->createLocality([
                    'country' => 'XX', 'names' => ['en' => 'Town D'], 'kind' => 'town', ...$bad,
                ]);
                $this->fail('Accepted: '.json_encode($bad));
            } catch (GeographyException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }

        $this->assertSame(0, GeoLocality::query()->count());
    }

    public function test_a_hand_change_to_a_carriers_record_is_marked_for_the_sync(): void
    {
        $locality = $this->locality('Town E', ['weight' => 3]);

        $this->editor()->updateLocality($locality, ['names' => ['en' => 'Town E', 'de' => 'Stadt E'], 'weight' => 3]);

        $locality->refresh();
        $this->assertSame(['names'], $locality->manual_fields);
        $this->assertTrue($locality->isManual('names'));
        $this->assertFalse($locality->isManual('weight'));

        $this->editor()->updateLocality($locality, ['weight' => 40]);
        $this->assertSame(['names', 'weight'], $locality->refresh()->manual_fields);
    }

    public function test_a_rename_into_another_locality_is_caught(): void
    {
        $this->locality('Town F');
        $other = $this->locality('Town G');

        $this->expectException(DuplicateLocality::class);

        $this->editor()->updateLocality($other, ['names' => ['en' => 'Town F']]);
    }

    public function test_a_locality_nothing_holds_is_deleted(): void
    {
        $locality = $this->locality('Town H');

        $this->assertSame(Removal::Deleted, $this->editor()->removeLocality($locality));
        $this->assertNull(GeoLocality::query()->find($locality->id));
        $this->assertSame(0, DB::table('geo_locality_names')->count());
    }

    public function test_a_held_locality_is_switched_off_and_leaves_the_suggestions(): void
    {
        Schema::create('test_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('locality_id');
        });
        $this->app->make(LocalityReferences::class)->table('test_points');

        $locality = $this->locality('Town I');
        DB::table('test_points')->insert(['locality_id' => $locality->id]);

        $this->assertSame(Removal::Deactivated, $this->editor()->removeLocality($locality));

        $locality->refresh();
        $this->assertFalse($locality->active);
        $this->assertTrue($locality->isManual('active'));
        $this->assertSame([], $this->app->make(LocalitySearch::class)->suggest('XX', 'town i', 'en'));

        $this->editor()->activate($locality);
        $this->assertCount(1, $this->app->make(LocalitySearch::class)->suggest('XX', 'town i', 'en'));
    }

    public function test_a_region_is_unique_in_its_country_and_kept_while_it_has_localities(): void
    {
        $region = $this->editor()->createRegion('XX', ['en' => 'Region A'], 'a1');
        $this->assertSame('A1', $region->code);

        try {
            $this->editor()->createRegion('XX', ['en' => 'region  a']);
            $this->fail('A second "Region A" was made.');
        } catch (GeographyException) {
        }

        $this->editor()->createRegion('XY', ['en' => 'Region A']);

        $locality = $this->editor()->createLocality(['country' => 'XX', 'names' => ['en' => 'Town J'], 'kind' => 'town', 'region_id' => $region->id]);

        try {
            $this->editor()->deleteRegion($region);
            $this->fail('A region with localities was deleted.');
        } catch (GeographyException) {
        }

        $this->editor()->removeLocality($locality);
        $this->editor()->deleteRegion($region);
        $this->assertNull(GeoRegion::query()->find($region->id));
    }

    public function test_a_region_renamed_by_hand_is_marked(): void
    {
        $region = $this->region('Region A');

        $this->editor()->updateRegion($region, ['names' => ['en' => 'Region A', 'de' => 'Gebiet A']]);

        $this->assertSame(['names'], $region->refresh()->manual_fields);
    }
}
