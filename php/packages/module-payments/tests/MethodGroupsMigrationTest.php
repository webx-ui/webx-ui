<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\MethodGroup;
use WebxUi\Payments\MethodGroups;
use WebxUi\Payments\Models\PaymentGroup;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * A site configured before the groups (groups spec §2.4): nothing written on its methods, and the
 * first item of its list stays first.
 */
final class MethodGroupsMigrationTest extends TestCase
{
    private const string MIGRATION = __DIR__.'/../database/migrations/2026_11_04_000001_create_payment_groups_table.php';

    #[Test]
    public function the_methods_are_not_touched(): void
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['enabled' => true, 'position' => 7]);

        $before = PaymentMethod::query()->orderBy('id')->get(['key', 'type', 'enabled', 'position', 'updated_at'])->toArray();

        $this->migrateAgain();

        $this->assertSame($before, PaymentMethod::query()->orderBy('id')->get(['key', 'type', 'enabled', 'position', 'updated_at'])->toArray());
        $this->assertSame(0, PaymentMethod::query()->whereNotNull('group_key')->count());
    }

    #[Test]
    public function the_groups_in_use_stand_by_their_first_enabled_method(): void
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['enabled' => true, 'position' => 3]);
        PaymentMethod::query()->where('key', OnReceipt::METHOD)->update(['enabled' => true, 'position' => 8]);
        $this->insertMethod('second-transfer', BankTransfer::KEY, 5);

        $this->migrateAgain();

        $this->assertSame(['transfer' => 3, 'on_receipt' => 8], PaymentGroup::query()->orderBy('position')->pluck('position', 'key')->all());
        $this->assertNull(PaymentGroup::query()->first()?->title);

        // The group nobody uses comes after, and the buyer's first item is still first.
        $this->assertSame(['transfer', 'on_receipt', 'online'], array_map(
            static fn (MethodGroup $group): string => $group->key,
            $this->app->make(MethodGroups::class)->all(),
        ));
    }

    #[Test]
    public function groups_tied_on_a_position_keep_the_order_of_the_config(): void
    {
        PaymentMethod::query()->update(['enabled' => true, 'position' => 0]);

        $this->migrateAgain();

        $this->assertSame(['on_receipt', 'transfer', 'online'], array_map(
            static fn (MethodGroup $group): string => $group->key,
            $this->app->make(MethodGroups::class)->all(),
        ));
    }

    #[Test]
    public function nothing_enabled_means_no_rows_and_the_order_of_the_config(): void
    {
        $this->migrateAgain();

        $this->assertSame(0, PaymentGroup::query()->count());
    }

    #[Test]
    public function a_method_of_a_type_nobody_registers_says_nothing(): void
    {
        $this->insertMethod('orphan', 'gone.card', 1);

        $this->migrateAgain();

        $this->assertSame(0, PaymentGroup::query()->count());
    }

    #[Test]
    public function a_site_without_groups_gets_no_rows(): void
    {
        $this->app['config']->set('webx-payments.groups', []);
        PaymentMethod::query()->update(['enabled' => true]);

        $this->migrateAgain();

        $this->assertSame(0, PaymentGroup::query()->count());
    }

    #[Test]
    public function the_rollback_takes_the_table_and_the_column(): void
    {
        (require self::MIGRATION)->down();

        $this->assertFalse(Schema::hasTable('payment_groups'));
        $this->assertFalse(Schema::hasColumn('payment_methods', 'group_key'));
        $this->assertTrue(Schema::hasTable('payment_methods'));

        (require self::MIGRATION)->up();

        $this->assertTrue(Schema::hasTable('payment_groups'));
        $this->assertTrue(Schema::hasColumn('payment_methods', 'group_key'));
    }

    /** As on a site that had the methods first: back to before the migration, and through it. */
    private function migrateAgain(): void
    {
        $migration = require self::MIGRATION;
        $migration->down();
        $migration->up();
    }

    private function insertMethod(string $key, string $type, int $position): void
    {
        DB::table('payment_methods')->insert([
            'key' => $key,
            'type' => $type,
            'title' => '{}',
            'enabled' => true,
            'position' => $position,
            'delivery_rule' => '{"mode":"any"}',
            'settings' => '{}',
        ]);
    }
}
