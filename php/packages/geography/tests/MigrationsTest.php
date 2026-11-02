<?php

declare(strict_types=1);

namespace WebxUi\Geography\Tests;

use Illuminate\Support\Facades\Schema;

final class MigrationsTest extends TestCase
{
    private const array TABLES = ['geo_countries', 'geo_regions', 'geo_region_aliases', 'geo_localities', 'geo_locality_names'];

    public function test_the_tables_roll_back_and_come_again(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->artisan('migrate:rollback')->assertSuccessful();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }

        $this->artisan('migrate')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('geo_locality_names'));
    }
}
