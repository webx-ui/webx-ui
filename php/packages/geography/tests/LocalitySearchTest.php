<?php

declare(strict_types=1);

namespace WebxUi\Geography\Tests;

use Illuminate\Support\Facades\DB;
use WebxUi\Geography\LocalitySearch;
use WebxUi\Geography\LocalitySuggestion;
use WebxUi\Geography\NameIndex;

final class LocalitySearchTest extends TestCase
{
    /** @return list<string> */
    private function suggest(string $query, string $locale = 'en', string $country = 'XX', int $limit = 10): array
    {
        return array_map(
            static fn (LocalitySuggestion $suggestion): string => $suggestion->label,
            $this->app->make(LocalitySearch::class)->suggest($country, $query, $locale, $limit),
        );
    }

    public function test_a_prefix_finds_localities_heaviest_first(): void
    {
        $region = $this->region('Region B');
        $this->locality('Town A', ['weight' => 5]);
        $this->locality('Town B', ['weight' => 50, 'region_id' => $region->id]);
        $this->locality('Village C', ['weight' => 100]);

        $this->assertSame(['Town B, Region B', 'Town A'], $this->suggest('tow'));
        $this->assertSame(['Town B, Region B', 'Town A'], $this->suggest('  TOWN '));
    }

    public function test_any_later_word_of_a_name_is_a_prefix_too(): void
    {
        $this->locality('Lower Town D');

        $this->assertSame(['Lower Town D'], $this->suggest('town'));
        $this->assertSame(['Lower Town D'], $this->suggest('town d'));
        $this->assertSame(['Lower Town D'], $this->suggest('d'));
        $this->assertSame([], $this->suggest('own'));
    }

    public function test_a_locality_with_several_matching_rows_comes_once(): void
    {
        $this->locality('Town A', ['names' => ['en' => 'Town A', 'de' => 'Town Ah']]);

        $this->assertSame(['Town A'], $this->suggest('town a'));
    }

    public function test_a_name_in_another_language_and_an_alias_find_it_too(): void
    {
        $locality = $this->locality('Town E', ['names' => ['en' => 'Town E', 'de' => 'Stadt E']]);
        NameIndex::addAlias($locality, 'T. Town E', 'carrier:carrier-a');

        $this->assertSame(['Town E'], $this->suggest('stadt'));
        $this->assertSame(['Stadt E'], $this->suggest('stadt', 'de'));
        $this->assertSame(['Town E'], $this->suggest('t town'));
    }

    public function test_the_label_is_in_the_language_asked_falling_back_to_the_main_one(): void
    {
        $region = $this->region('Region B');
        $region->update(['names' => ['en' => 'Region B', 'de' => 'Gebiet B']]);
        $this->locality('Town F', ['region_id' => $region->id, 'district' => ['en' => 'District C']]);

        $this->assertSame(['Town F, District C, Gebiet B'], $this->suggest('town f', 'de'));
        $this->assertSame(['Town F, District C, Region B'], $this->suggest('town f', 'fr'));
    }

    public function test_an_empty_query_gives_the_heaviest_of_the_country(): void
    {
        foreach (range(1, 12) as $weight) {
            $this->locality("Town {$weight}", ['weight' => $weight]);
        }
        $this->locality('Elsewhere', ['country_code' => 'XY', 'weight' => 1000]);

        $suggestions = $this->suggest('');

        $this->assertCount(10, $suggestions);
        $this->assertSame(['Town 12', 'Town 11', 'Town 10'], array_slice($suggestions, 0, 3));
        $this->assertSame(['Town 12', 'Town 11'], $this->suggest('   ', limit: 2));
    }

    public function test_a_switched_off_locality_is_not_suggested(): void
    {
        $this->locality('Town A');
        $this->locality('Town B', ['active' => false]);

        $this->assertSame(['Town A'], $this->suggest('town'));
        $this->assertSame(['Town A'], $this->suggest(''));
    }

    public function test_only_the_country_asked_for(): void
    {
        $this->locality('Town A');
        $this->locality('Town Z', ['country_code' => 'XY']);

        $this->assertSame(['Town A'], $this->suggest('town', country: 'xx'));
        $this->assertSame(['Town Z'], $this->suggest('town', country: 'XY'));
    }

    public function test_wildcards_in_the_query_are_letters(): void
    {
        $this->locality('Town A');

        $this->assertSame([], $this->suggest('%'));
        $this->assertSame([], $this->suggest('t_wn'));
    }

    public function test_a_rename_moves_the_search_rows_and_keeps_aliases(): void
    {
        $locality = $this->locality('Old Name');
        NameIndex::addAlias($locality, 'Carrier Spelling', 'carrier:carrier-a');

        $locality->update(['names' => ['en' => 'New Name']]);

        $this->assertSame([], $this->suggest('old'));
        $this->assertSame(['New Name'], $this->suggest('new'));
        $this->assertSame(['New Name'], $this->suggest('spelling'));
    }

    public function test_suggestions_take_two_queries_however_many_match(): void
    {
        $region = $this->region('Region B');

        foreach (range(1, 30) as $index) {
            $this->locality("Town {$index}", ['region_id' => $region->id, 'weight' => $index]);
        }

        DB::enableQueryLog();
        $this->suggest('town');
        $this->assertCount(2, DB::getQueryLog());
    }
}
