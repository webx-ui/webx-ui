<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class MigrationsTest extends TestCase
{
    private const array TABLES = ['payment_groups', 'payment_methods', 'payment_selections', 'payments', 'payment_gateway_events'];

    #[Test]
    public function the_tables_are_there_with_the_indexes_of_the_spec(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} is there");
        }

        $this->assertSame(
            [
                ['created_at'], ['gateway', 'created_at'], ['id'], ['kind', 'created_at'], ['method_key', 'event_key'],
                ['order_id', 'id'], ['outcome', 'created_at'], ['payment_id', 'id'], ['signature', 'created_at'], ['trace'],
            ],
            $this->indexes('payment_gateway_events'),
        );
        $this->assertSame(
            [['hold_expires_at'], ['id'], ['order_id'], ['problem'], ['status', 'created_at'], ['type', 'gateway_ref'], ['uuid']],
            $this->indexes('payments'),
        );
        $this->assertSame([['id'], ['key'], ['type']], $this->indexes('payment_methods'));
        $this->assertSame([['cart_id'], ['id']], $this->indexes('payment_selections'));
        $this->assertSame([['id'], ['key']], $this->indexes('payment_groups'));
    }

    #[Test]
    public function the_migrations_roll_back_and_leave_the_orders(): void
    {
        $this->artisan('migrate:rollback', ['--path' => realpath(__DIR__.'/../database/migrations'), '--realpath' => true])->run();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} is gone");
        }

        $this->assertTrue(Schema::hasTable('orders'));
    }

    /** @return list<list<string>> */
    private function indexes(string $table): array
    {
        $indexes = array_map(static fn (array $index): array => $index['columns'], Schema::getIndexes($table));
        usort($indexes, static fn (array $a, array $b): int => implode(',', $a) <=> implode(',', $b));

        return $indexes;
    }
}
