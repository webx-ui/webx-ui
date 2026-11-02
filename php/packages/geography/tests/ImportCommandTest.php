<?php

declare(strict_types=1);

namespace WebxUi\Geography\Tests;

use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;

final class ImportCommandTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'geo').'.jsonl';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    /** @param  list<array<string, mixed>|string>  $records */
    private function write(array $records): void
    {
        file_put_contents($this->file, implode("\n", array_map(
            static fn (array|string $record): string => is_string($record) ? $record : (string) json_encode($record),
            $records,
        )));
    }

    public function test_records_become_localities_regions_and_a_switched_off_country(): void
    {
        $this->write([
            ['country' => 'XX', 'name' => 'Town A', 'kind' => 'city', 'region' => 'Region A', 'lat' => 1.5, 'lng' => 2.5],
            ['country' => 'XX', 'name' => 'Town B', 'kind' => 'village', 'region' => 'region a', 'names' => ['de' => 'Dorf B']],
            ['country' => 'XX', 'name' => 'Town C', 'kind' => 'hamlet'],
            '{not json',
        ]);

        // A line that is not JSON stops the whole file before anything is written.
        $this->artisan('webx:geo:import', ['file' => $this->file])->assertFailed();
        $this->assertSame(0, GeoLocality::query()->count());

        $this->write([
            ['country' => 'XX', 'name' => 'Town A', 'kind' => 'city', 'region' => 'Region A', 'lat' => 1.5, 'lng' => 2.5],
            ['country' => 'XX', 'name' => 'Town B', 'kind' => 'village', 'region' => 'region a', 'names' => ['de' => 'Dorf B']],
            ['country' => 'XX', 'name' => 'Town C', 'kind' => 'hamlet'],
            '"a string"',
        ]);

        $this->artisan('webx:geo:import', ['file' => $this->file])
            ->expectsOutputToContain('2 created, 0 updated, 0 unchanged, 1 regions created, 2 skipped.')
            ->assertSuccessful();

        $this->assertFalse(GeoCountry::query()->findOrFail('XX')->active);
        $this->assertSame(1, GeoRegion::query()->count());

        $town = GeoLocality::query()->where('name_key', 'town b')->firstOrFail();
        $this->assertSame('import', $town->created_by);
        $this->assertSame(['en' => 'Town B', 'de' => 'Dorf B'], $town->names);
        $this->assertSame(10, $town->weight);
    }

    public function test_the_same_file_twice_changes_nothing(): void
    {
        $this->write([['country' => 'XX', 'name' => 'Town A', 'kind' => 'city', 'region' => 'Region A']]);

        $this->artisan('webx:geo:import', ['file' => $this->file, '--activate' => true])->assertSuccessful();
        $this->artisan('webx:geo:import', ['file' => $this->file])
            ->expectsOutputToContain('0 created, 0 updated, 1 unchanged, 0 regions created, 0 skipped.')
            ->assertSuccessful();

        $this->assertTrue(GeoCountry::query()->findOrFail('XX')->active);
        $this->assertSame(1, GeoLocality::query()->count());
    }

    public function test_an_import_binds_to_a_hand_made_locality_and_keeps_its_name(): void
    {
        $region = $this->region('Region A');
        $manual = $this->locality('Town A', ['region_id' => $region->id, 'kind' => 'city', 'created_by' => 'manual']);

        $this->write([['country' => 'XX', 'name' => 'Town A', 'kind' => 'city', 'region' => 'Region A', 'names' => ['de' => 'Stadt A'], 'lat' => 3, 'lng' => 4]]);

        $this->artisan('webx:geo:import', ['file' => $this->file])
            ->expectsOutputToContain('0 created, 0 updated, 1 unchanged')
            ->assertSuccessful();

        $manual->refresh();
        $this->assertSame(['en' => 'Town A'], $manual->names);
        $this->assertNull($manual->lat);
    }

    public function test_an_import_fills_a_carriers_record_except_what_was_changed_by_hand(): void
    {
        $locality = $this->locality('Town A', ['kind' => 'city', 'manual_fields' => ['lat']]);

        $this->write([['country' => 'XX', 'name' => 'Town A', 'kind' => 'city', 'names' => ['de' => 'Stadt A'], 'lat' => 3, 'lng' => 4]]);

        $this->artisan('webx:geo:import', ['file' => $this->file])->assertSuccessful();

        $locality->refresh();
        $this->assertSame(['en' => 'Town A', 'de' => 'Stadt A'], $locality->names);
        $this->assertNull($locality->lat);
        $this->assertSame(4.0, $locality->lng);
    }

    public function test_a_dry_run_saves_nothing(): void
    {
        file_put_contents($this->file, (string) json_encode([['country' => 'XX', 'name' => 'Town A', 'kind' => 'city']]));

        $this->artisan('webx:geo:import', ['file' => $this->file, '--dry-run' => true])
            ->expectsOutputToContain('Dry run, nothing saved: 1 created')
            ->assertSuccessful();

        $this->assertSame(0, GeoLocality::query()->count());
        $this->assertSame(0, GeoCountry::query()->count());
    }

    public function test_a_missing_file_fails(): void
    {
        $this->artisan('webx:geo:import', ['file' => '/nowhere/geo.json'])->assertFailed();
    }
}
