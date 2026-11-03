<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Geography\Editor;
use WebxUi\Geography\Removal;

final class MigrationsTest extends TestCase
{
    private const array TABLES = [
        'delivery_carriers', 'delivery_methods', 'delivery_method_localities', 'delivery_sync_runs',
        'delivery_carrier_localities', 'delivery_points', 'delivery_popular_localities', 'delivery_streets', 'delivery_selections',
    ];

    #[Test]
    public function the_shop_is_a_carrier_from_the_first_day(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} is there");
        }

        $this->assertSame(['store'], DB::table('delivery_carriers')->pluck('key')->all());
    }

    #[Test]
    public function the_migrations_roll_back_and_leave_the_geography(): void
    {
        $this->artisan('migrate:rollback', ['--path' => realpath(__DIR__.'/../database/migrations'), '--realpath' => true])->run();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} is gone");
        }

        $this->assertTrue(Schema::hasTable('geo_localities'));
    }

    #[Test]
    public function a_locality_a_point_stands_on_is_switched_off_not_deleted(): void
    {
        $town = $this->town('Town A');
        $point = $this->point($town);

        $this->assertSame(Removal::Deactivated, $this->app->make(Editor::class)->removeLocality($town));
        $this->assertSame($town->id, DeliveryPoint::query()->findOrFail($point->id)->locality_id);
    }

    #[Test]
    public function a_points_search_string_is_its_number_titles_and_address_normalised(): void
    {
        $point = $this->point($this->town('Town A'), 'store', [
            'number' => '12',
            'titles' => ['en' => 'Store “A”', 'pl' => 'Sklep A'],
            'address' => ['en' => '1 Example St.'],
        ]);

        $this->assertSame('12 store a sklep a 1 example st', $point->search);
    }
}
