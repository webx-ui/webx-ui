<?php

declare(strict_types=1);

namespace WebxUi\Geography\Tests;

use Illuminate\Support\Facades\DB;
use WebxUi\Geography\Editor;
use WebxUi\Geography\GeographyException;
use WebxUi\Geography\Matcher;
use WebxUi\Geography\Matching\BindingMethod;
use WebxUi\Geography\Matching\BindingStatus;
use WebxUi\Geography\Matching\Candidate;
use WebxUi\Geography\Matching\CarrierBindings;
use WebxUi\Geography\Matching\LeadingCarriers;
use WebxUi\Geography\Matching\LocalityRecord;
use WebxUi\Geography\Matching\MatchResult;
use WebxUi\Geography\Matching\QueueReason;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoLocalityName;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Geography\Models\GeoRegionAlias;

/**
 * The cases of §4.3–§4.4: what a carrier's locality binds to, and what waits for a person.
 * `carrier-a` leads country `XX` in every test unless the test says otherwise; `carrier-b` follows.
 */
final class MatcherTest extends TestCase
{
    private const string LEAD = 'carrier-a';

    private const string FOLLOWER = 'carrier-b';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-geography.countries.XX.source', self::LEAD);
    }

    private function matcher(): Matcher
    {
        return $this->app->make(Matcher::class);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function record(string $id, string $name, array $fields = []): LocalityRecord
    {
        return LocalityRecord::fromArray([
            'externalId' => $id,
            'country' => 'XX',
            'name' => $name,
            'kind' => 'village',
            'region' => 'Region A',
            ...$fields,
        ]);
    }

    // Step 3 — exact

    public function test_one_locality_of_the_same_region_type_and_name_is_bound_exactly(): void
    {
        $region = $this->region('Region A');
        $village = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village']);

        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Village A'));

        $this->assertSame(BindingStatus::Matched, $result->status);
        $this->assertSame($village->id, $result->localityId);
        $this->assertSame(BindingMethod::AutoExact, $result->method);
    }

    public function test_namesake_villages_of_different_regions_are_told_apart_by_the_region(): void
    {
        $regionA = $this->region('Region A');
        $regionB = $this->region('Region B');
        $this->locality('Village A', ['region_id' => $regionA->id, 'kind' => 'village']);
        $inB = $this->locality('Village A', ['region_id' => $regionB->id, 'kind' => 'village']);

        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Village A', ['region' => 'Region B']));

        $this->assertSame($inB->id, $result->localityId);
        $this->assertSame(BindingMethod::AutoExact, $result->method);
        $this->assertSame(2, GeoLocality::query()->count());
    }

    public function test_the_carriers_spelling_becomes_an_alias_and_the_next_carrier_matches_on_it(): void
    {
        $region = $this->region('Region A');
        $town = $this->locality('Town A', ['region_id' => $region->id, 'kind' => 'town']);

        // The first carrier spells the town two ways; the shared record knows one.
        $this->matcher()->match(self::FOLLOWER, $this->record('1', 'Town A', ['kind' => 'town', 'names' => ['de' => 'Stadt-A']]));

        $alias = GeoLocalityName::query()->where('locality_id', $town->id)->where('kind', GeoLocalityName::ALIAS)->sole();
        $this->assertSame('stadt-a', $alias->name_key);
        $this->assertSame('carrier:carrier-b', $alias->source);

        $result = $this->matcher()->match('carrier-c', $this->record('9', 'Stadt-A', ['kind' => 'town']));
        $this->assertSame($town->id, $result->localityId);
        $this->assertSame(BindingMethod::AutoExact, $result->method);
    }

    public function test_a_district_the_carrier_gives_picks_the_namesake_of_that_district(): void
    {
        $region = $this->region('Region A');
        $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village', 'district' => ['en' => 'District C']]);
        $inD = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village', 'district' => ['en' => 'District D']]);

        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Village A', ['district' => 'District D']));

        $this->assertSame($inD->id, $result->localityId);
        $this->assertSame(BindingMethod::AutoExact, $result->method);
    }

    // Step 4 — namesakes in one region

    public function test_namesakes_of_one_region_are_told_apart_by_coordinates_within_the_radius(): void
    {
        $region = $this->region('Region A');
        $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village', 'lat' => 10.0, 'lng' => 20.0]);
        $east = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village', 'lat' => 10.0, 'lng' => 20.5]);

        // ~2 km from the eastern one, ~53 km from the other.
        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Village A', ['lat' => 10.01, 'lng' => 20.49]));

        $this->assertSame($east->id, $result->localityId);
        $this->assertSame(BindingMethod::AutoCoords, $result->method);
    }

    public function test_namesakes_the_coordinates_do_not_settle_wait_with_the_candidates(): void
    {
        $region = $this->region('Region A');
        $west = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village', 'lat' => 10.0, 'lng' => 20.0]);
        $east = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village', 'lat' => 10.0, 'lng' => 20.5]);

        $far = $this->matcher()->match(self::LEAD, $this->record('7', 'Village A', ['lat' => 11.0, 'lng' => 20.45]));
        $blind = $this->matcher()->match(self::LEAD, $this->record('8', 'Village A'));

        // Even the leading carrier does not make a third one: a namesake is a person's call.
        $this->assertSame(BindingStatus::Pending, $far->status);
        $this->assertSame(QueueReason::Ambiguous, $far->reason);
        $this->assertSame([$east->id, $west->id], array_map(static fn ($candidate) => $candidate->localityId, $far->candidates));
        $this->assertNotNull($far->candidates[0]->distanceKm);
        $this->assertSame(QueueReason::Ambiguous, $blind->reason);
        $this->assertNull($blind->candidates[0]->distanceKm);
        $this->assertSame(2, GeoLocality::query()->count());
    }

    // Step 5 — the type differs

    public function test_another_type_within_the_radius_is_bound_by_coordinates(): void
    {
        $region = $this->region('Region A');
        $town = $this->locality('Place A', ['region_id' => $region->id, 'kind' => 'town', 'lat' => 10.0, 'lng' => 20.0]);

        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Place A', ['kind' => 'township', 'lat' => 10.01, 'lng' => 20.01]));

        $this->assertSame($town->id, $result->localityId);
        $this->assertSame(BindingMethod::AutoCoords, $result->method);
    }

    public function test_another_type_out_of_the_radius_or_without_coordinates_waits_as_a_kind_mismatch(): void
    {
        $region = $this->region('Region A');
        $town = $this->locality('Place A', ['region_id' => $region->id, 'kind' => 'town', 'lat' => 10.0, 'lng' => 20.0]);

        $far = $this->matcher()->match(self::LEAD, $this->record('7', 'Place A', ['kind' => 'township', 'lat' => 10.5, 'lng' => 20.0]));
        $blind = $this->matcher()->match(self::FOLLOWER, $this->record('8', 'Place A', ['kind' => 'township']));

        foreach ([$far, $blind] as $result) {
            $this->assertSame(BindingStatus::Pending, $result->status);
            $this->assertSame(QueueReason::KindMismatch, $result->reason);
            $this->assertSame($town->id, $result->candidates[0]->localityId);
        }

        $this->assertEqualsWithDelta(55.6, $far->candidates[0]->distanceKm, 0.5);
        $this->assertSame(1, GeoLocality::query()->count());
    }

    public function test_the_radius_is_a_setting(): void
    {
        $this->app['config']->set('webx-geography.match_radius_km', 60);
        $region = $this->region('Region A');
        $town = $this->locality('Place A', ['region_id' => $region->id, 'kind' => 'town', 'lat' => 10.0, 'lng' => 20.0]);

        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Place A', ['kind' => 'township', 'lat' => 10.5, 'lng' => 20.0]));

        $this->assertSame($town->id, $result->localityId);
    }

    // Step 2 — the region

    public function test_an_unknown_region_from_a_following_carrier_waits_and_makes_nothing(): void
    {
        $this->region('Region A');

        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Village A', ['region' => 'Region Q']));

        $this->assertSame(BindingStatus::Pending, $result->status);
        $this->assertSame(QueueReason::RegionUnknown, $result->reason);
        $this->assertNull($result->localityId);
        $this->assertSame(1, GeoRegion::query()->count());
        $this->assertSame(0, GeoLocality::query()->count());
    }

    public function test_the_leading_carrier_brings_its_region_and_locality_into_an_empty_country(): void
    {
        $result = $this->matcher()->match(self::LEAD, $this->record('7', 'Village A', [
            'region' => 'Region Q', 'regionNames' => ['de' => 'Bezirk Q'], 'names' => ['de' => 'Dorf A'],
            'district' => 'District C', 'lat' => 10.5, 'lng' => 20.25,
        ]));

        $this->assertSame(BindingMethod::Created, $result->method);
        $locality = GeoLocality::query()->with('region')->findOrFail($result->localityId);
        $this->assertSame('carrier:carrier-a', $locality->created_by);
        $this->assertSame(['en' => 'Village A', 'de' => 'Dorf A'], $locality->names);
        $this->assertSame(['en' => 'District C'], $locality->district);
        $this->assertSame(10, $locality->weight);
        $this->assertSame(['en' => 'Region Q', 'de' => 'Bezirk Q'], $locality->region?->names);
        // The other language's spelling of the region is matched by alias from now on.
        $this->assertSame(['bezirk q'], GeoRegionAlias::query()->pluck('name_key')->all());
        $this->assertFalse($locality->country()->firstOrFail()->active);
    }

    public function test_a_region_spelled_by_its_alias_is_found(): void
    {
        $region = $this->region('Region A');
        GeoRegionAlias::query()->create(['region_id' => $region->id, 'name_key' => 'region a obl', 'source' => 'carrier:carrier-c']);
        $village = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village']);

        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Village A', ['region' => 'Region A Obl.']));

        $this->assertSame($village->id, $result->localityId);
    }

    public function test_a_record_without_a_region_is_matched_across_the_country(): void
    {
        $region = $this->region('Region A');
        $village = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village']);

        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Village A', ['region' => null]));

        $this->assertSame($village->id, $result->localityId);
    }

    // Step 6 — nothing found

    public function test_unmatched_from_a_following_carrier_goes_to_the_queue(): void
    {
        $this->region('Region A');

        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Village Z'));

        $this->assertSame(BindingStatus::Pending, $result->status);
        $this->assertSame(QueueReason::NotFound, $result->reason);
        $this->assertSame([], $result->candidates);
        $this->assertSame(0, GeoLocality::query()->count());
    }

    public function test_the_first_carrier_to_sync_a_country_leads_it(): void
    {
        $this->app['config']->set('webx-geography.countries.XX', []);

        $first = $this->matcher()->match(self::FOLLOWER, $this->record('1', 'Village A'));
        $second = $this->matcher()->match('carrier-c', $this->record('2', 'Village Z'));

        $this->assertSame(self::FOLLOWER, $this->app->make(LeadingCarriers::class)->leader('XX'));
        $this->assertSame(BindingMethod::Created, $first->method);
        $this->assertSame(QueueReason::NotFound, $second->reason);
    }

    // Step 1 — settled bindings

    public function test_a_manual_binding_is_never_overwritten(): void
    {
        $region = $this->region('Region A');
        $chosen = $this->locality('Hamlet B', ['region_id' => $region->id, 'kind' => 'village']);
        $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village']);

        // The record matches "Village A" exactly now; a person bound it to "Hamlet B" before.
        $current = MatchResult::fromRow('matched', $chosen->id, 'manual');
        $result = $this->matcher()->match(self::LEAD, $this->record('7', 'Village A'), $current);

        $this->assertTrue($result->kept);
        $this->assertSame($chosen->id, $result->localityId);
        $this->assertSame(BindingMethod::Manual, $result->method);
    }

    public function test_an_ignored_record_stays_ignored_and_a_pending_one_is_matched_again(): void
    {
        $result = $this->matcher()->match(self::FOLLOWER, $this->record('7', 'Village A'), MatchResult::ignored());
        $this->assertTrue($result->kept);
        $this->assertSame(BindingStatus::Ignored, $result->status);

        // Waiting for its region; a person adds the region and the town, the next run binds it.
        $pending = $this->matcher()->match(self::FOLLOWER, $this->record('8', 'Village A'));
        $this->assertSame(QueueReason::RegionUnknown, $pending->reason);

        $editor = $this->app->make(Editor::class);
        $region = $editor->createRegion('XX', ['en' => 'Region A']);
        $village = $editor->createLocality(['country' => 'XX', 'names' => ['en' => 'Village A'], 'kind' => 'village', 'region_id' => $region->id]);

        $again = $this->matcher()->match(self::FOLLOWER, $this->record('8', 'Village A'), $pending);
        $this->assertFalse($again->kept);
        $this->assertSame($village->id, $again->localityId);
    }

    // Decision 42 — hand-made localities

    public function test_the_leading_carrier_binds_to_a_hand_made_locality_without_a_duplicate_and_keeps_its_name(): void
    {
        $editor = $this->app->make(Editor::class);
        $region = $editor->createRegion('XX', ['en' => 'Region A']);
        $town = $editor->createLocality(['country' => 'XX', 'names' => ['en' => 'Town A'], 'kind' => 'town', 'region_id' => $region->id]);

        $result = $this->matcher()->match(self::LEAD, $this->record('7', 'Town A', [
            'kind' => 'town', 'names' => ['en' => 'Town A', 'de' => 'Stadt A'], 'regionNames' => ['de' => 'Bezirk A'],
            'lat' => 10.0, 'lng' => 20.0,
        ]));

        $this->assertSame($town->id, $result->localityId);
        $this->assertSame(BindingMethod::AutoExact, $result->method);
        $this->assertSame(1, GeoLocality::query()->count());

        $town->refresh();
        $this->assertSame('manual', $town->created_by);
        $this->assertSame(['en' => 'Town A'], $town->names);
        $this->assertNull($town->lat);
        // The carrier's other spelling finds the town next time, without becoming its name.
        $this->assertSame(['stadt a'], GeoLocalityName::query()->where('locality_id', $town->id)->where('kind', GeoLocalityName::ALIAS)->pluck('name_key')->all());
        // The hand-made region is not renamed either; the carrier's spelling is an alias.
        $this->assertSame(['en' => 'Region A'], $region->refresh()->names);
        $this->assertSame(['bezirk a'], GeoRegionAlias::query()->pluck('name_key')->all());
    }

    public function test_the_leading_carrier_completes_its_own_records_but_not_fields_changed_by_hand(): void
    {
        $region = $this->region('Region A');
        $own = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village']);
        $edited = $this->locality('Village B', ['region_id' => $region->id, 'kind' => 'village']);
        $this->app->make(Editor::class)->updateLocality($edited, ['names' => ['en' => 'Village B'], 'lat' => 1.5]);
        $edited->markManual(['names'])->save();

        $this->matcher()->matchPage(self::LEAD, [
            $this->record('1', 'Village A', ['names' => ['de' => 'Dorf A'], 'lat' => 10.0, 'lng' => 20.0]),
            $this->record('2', 'Village B', ['names' => ['de' => 'Dorf B'], 'lat' => 11.0, 'lng' => 21.0]),
        ]);

        $own->refresh();
        $this->assertSame(['en' => 'Village A', 'de' => 'Dorf A'], $own->names);
        $this->assertSame(10.0, $own->lat);

        $edited->refresh();
        $this->assertSame(['en' => 'Village B'], $edited->names);
        $this->assertSame(1.5, $edited->lat);
        $this->assertSame(21.0, $edited->lng);
    }

    public function test_a_following_carrier_never_writes_into_the_shared_record(): void
    {
        $region = $this->region('Region A');
        $village = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village']);

        $this->matcher()->match(self::FOLLOWER, $this->record('1', 'Village A', ['names' => ['de' => 'Dorf A'], 'lat' => 10.0, 'lng' => 20.0]));

        $village->refresh();
        $this->assertSame(['en' => 'Village A'], $village->names);
        $this->assertNull($village->lat);
    }

    public function test_an_imported_locality_is_a_candidate_too(): void
    {
        $region = $this->region('Region A');
        $village = $this->locality('Village A', ['region_id' => $region->id, 'kind' => 'village', 'created_by' => 'import']);

        $this->assertSame($village->id, $this->matcher()->match(self::LEAD, $this->record('1', 'Village A'))->localityId);
    }

    // Pages

    public function test_two_records_of_one_carrier_spelled_alike_are_two_towns(): void
    {
        // A carrier never lists one town twice: namesakes of one region it tells apart only by a
        // qualifier («Village N (District A)», «Village N (District B)») stay two towns.
        $results = $this->matcher()->matchPage(self::LEAD, [
            $this->record('1', 'Village N'),
            $this->record('2', 'village  n.'),
        ]);

        $this->assertSame(BindingMethod::Created, $results['1']->method);
        $this->assertSame(BindingMethod::Created, $results['2']->method);
        $this->assertNotSame($results['1']->localityId, $results['2']->localityId);
        $this->assertSame(2, GeoLocality::query()->count());

        // Another carrier spelling it alike, with nothing to tell the two apart, waits for a person.
        $other = $this->matcher()->match(self::FOLLOWER, $this->record('9', 'Village N'));

        $this->assertSame(QueueReason::Ambiguous, $other->reason);
        $this->assertCount(2, $other->candidates);
    }

    public function test_a_town_bound_to_one_record_of_a_carrier_is_no_candidate_for_its_next(): void
    {
        $region = $this->region('Region A');
        $manual = $this->locality('Village N', ['region_id' => $region->id, 'kind' => 'village']);

        // The follower's first record takes the hand-made town; the second waits, not merged.
        $follower = $this->matcher()->matchPage(self::FOLLOWER, [
            $this->record('1', 'Village N'),
            $this->record('2', 'Village N'),
        ]);

        $this->assertSame($manual->id, $follower['1']->localityId);
        $this->assertSame(BindingMethod::AutoExact, $follower['1']->method);
        $this->assertSame(BindingStatus::Pending, $follower['2']->status);
        $this->assertSame(QueueReason::NotFound, $follower['2']->reason);

        // The leading carrier makes the second town instead.
        $lead = $this->matcher()->matchPage(self::LEAD, [
            $this->record('1', 'Village N'),
            $this->record('2', 'Village N'),
        ]);

        $this->assertSame($manual->id, $lead['1']->localityId);
        $this->assertSame(BindingMethod::Created, $lead['2']->method);
        $this->assertNotSame($manual->id, $lead['2']->localityId);
    }

    public function test_a_town_a_stored_binding_holds_is_left_out_but_the_record_itself_keeps_matching(): void
    {
        $region = $this->region('Region A');
        $held = $this->locality('Village N', ['region_id' => $region->id, 'kind' => 'village']);
        $bindings = new class($held->id) implements CarrierBindings
        {
            /** @var list<array{string, list<int>, list<string>}> */
            public array $asked = [];

            public function __construct(private readonly int $held) {}

            public function taken(string $carrier, array $localityIds, array $except): array
            {
                $this->asked[] = [$carrier, $localityIds, $except];

                return $carrier === 'carrier-b' && ! in_array('1', $except, true) ? [$this->held] : [];
            }
        };
        $this->app->instance(CarrierBindings::class, $bindings);

        // Record 1 of carrier-b holds the town in the store: record 2 does not get it.
        $second = $this->matcher()->match(self::FOLLOWER, $this->record('2', 'Village N'));
        // Record 1 itself, matched again, is not kept off its own town; another carrier is not either.
        $first = $this->matcher()->match(self::FOLLOWER, $this->record('1', 'Village N'));
        $other = $this->matcher()->match('carrier-c', $this->record('5', 'Village N'));

        $this->assertSame(QueueReason::NotFound, $second->reason);
        $this->assertSame($held->id, $first->localityId);
        $this->assertSame($held->id, $other->localityId);
        $this->assertSame(['carrier-b', [$held->id], ['2']], $bindings->asked[0]);
    }

    public function test_a_page_of_known_towns_costs_a_fixed_number_of_queries(): void
    {
        $region = $this->region('Region A');
        $records = [];

        foreach (range(1, 40) as $n) {
            $this->locality("Village {$n}", ['region_id' => $region->id, 'kind' => 'village']);
            $records[] = $this->record((string) $n, "Village {$n}");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $results = $this->matcher()->matchPage(self::FOLLOWER, $records);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(40, array_filter($results, static fn (MatchResult $result): bool => $result->method === BindingMethod::AutoExact));
        // Regions with aliases (2), name rows (1), localities (1).
        $this->assertLessThanOrEqual(4, $queries);
    }

    // The queue

    public function test_binding_in_the_queue_is_manual_and_teaches_both_spellings(): void
    {
        $region = $this->region('Region A');
        $town = $this->locality('Town A', ['region_id' => $region->id, 'kind' => 'town']);
        $record = $this->record('7', 'Town-on-A', ['region' => 'Area A', 'kind' => 'town']);

        $this->assertSame(QueueReason::RegionUnknown, $this->matcher()->match(self::FOLLOWER, $record)->reason);

        $result = $this->matcher()->bind(self::FOLLOWER, $record, $town);
        $this->assertSame(BindingMethod::Manual, $result->method);
        $this->assertSame($town->id, $result->localityId);

        // Another record of that carrier, spelled the same, needs nobody.
        $next = $this->matcher()->match('carrier-c', $this->record('8', 'Town-on-A', ['region' => 'Area A', 'kind' => 'town']));
        $this->assertSame($town->id, $next->localityId);
        $this->assertSame(BindingMethod::AutoExact, $next->method);
    }

    public function test_making_a_shared_locality_from_the_queue(): void
    {
        $record = $this->record('7', 'Village Z', ['region' => 'Region Q', 'lat' => 1.0, 'lng' => 2.0]);
        $this->assertSame(QueueReason::RegionUnknown, $this->matcher()->match(self::FOLLOWER, $record)->reason);

        $result = $this->matcher()->create(self::FOLLOWER, $record);

        $this->assertSame(BindingMethod::Created, $result->method);
        $locality = GeoLocality::query()->with('region')->findOrFail($result->localityId);
        $this->assertSame('carrier:carrier-b', $locality->created_by);
        $this->assertSame('Region Q', $locality->region?->name('en'));
    }

    public function test_a_queue_row_round_trips(): void
    {
        $result = MatchResult::pending(QueueReason::Ambiguous, [new Candidate(3, 1.25)]);
        $row = $result->toArray();
        $back = MatchResult::fromRow($row['status'], $row['locality_id'], $row['method'], $row['reason'], $row['candidates']);

        $this->assertEquals($result, $back);

        $record = $this->record('7', 'Village A', ['names' => ['de' => 'Dorf A'], 'features' => ['courier'], 'lat' => 1.5]);
        $this->assertEquals($record, LocalityRecord::fromArray($record->toArray()));
    }

    public function test_a_broken_record_or_carrier_key_is_refused(): void
    {
        try {
            $this->matcher()->match(self::LEAD, $this->record('7', 'Village A', ['country' => 'X1']));
            $this->fail('A record without a country was matched.');
        } catch (GeographyException $exception) {
            $this->assertStringContainsString('Record 7', $exception->getMessage());
        }

        $this->expectException(GeographyException::class);
        $this->matcher()->match('Carrier A', $this->record('7', 'Village A'));
    }
}
