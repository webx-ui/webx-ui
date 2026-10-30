<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\StatusColor;

/**
 * The tables of spec §3 and the set of statuses a site starts with (§14): there after `migrate`,
 * gone after a rollback, and back the same after migrating again.
 */
final class MigrationTest extends TestCase
{
    /** Every table of the order, in the order they are made. */
    private const array TABLES = [
        'order_public_statuses',
        'order_statuses',
        'orders',
        'order_items',
        'order_adjustments',
        'order_tenders',
        'order_benefits',
        'order_milestones',
        'order_refunds',
        'order_status_changes',
        // The edits of the lines (§38.1).
        'order_revisions',
        // The checkout's draft (§21.1), made whether the checkout is on or not.
        'order_checkout_drafts',
    ];

    #[Test]
    public function it_makes_every_table_and_takes_them_back(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} is missing");
        }

        $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} outlived the rollback");
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertSame(5, PublicStatus::query()->count());
        $this->assertSame(8, OrderStatus::query()->count());
    }

    #[Test]
    public function the_orders_table_has_the_columns_of_the_spec(): void
    {
        $this->assertSame(
            ['id', 'number', 'idempotency_key', 'user_id', 'cart_id', 'locale', 'currency', 'status_id', 'subtotal', 'adjustments_total', 'total', 'tenders_total', 'payable', 'refunded_total', 'customer_first_name', 'customer_last_name', 'customer_phone', 'customer_phone_key', 'customer_email', 'recipient', 'comment', 'manager_note', 'delivery', 'payment', 'placed_at', 'awaiting_payment', 'payment_failed_at', 'paid_at', 'received_at', 'cancelled_at', 'refunded_at', 'created_at', 'updated_at', 'paid_total', 'balance', 'items_revision', 'items_changed_at'],
            Schema::getColumnListing('orders'),
        );
    }

    #[Test]
    public function the_indexes_the_list_and_the_search_read_are_there(): void
    {
        $indexes = array_map(
            static fn (array $index): string => ($index['unique'] ? 'unique ' : '').implode(',', $index['columns']),
            Schema::getIndexes('orders'),
        );

        foreach (['unique number', 'unique idempotency_key', 'status_id,placed_at', 'awaiting_payment,placed_at', 'user_id,placed_at', 'customer_email', 'customer_phone_key', 'placed_at', 'balance,placed_at'] as $index) {
            $this->assertContains($index, $indexes);
        }

        $milestones = array_map(static fn (array $index): string => implode(',', $index['columns']), Schema::getIndexes('order_milestones'));
        $this->assertContains('order_id,milestone', $milestones);

        $history = array_map(static fn (array $index): string => implode(',', $index['columns']), Schema::getIndexes('order_status_changes'));
        $this->assertContains('order_id,id', $history);

        $revisions = array_map(static fn (array $index): string => ($index['unique'] ? 'unique ' : '').implode(',', $index['columns']), Schema::getIndexes('order_revisions'));
        $this->assertContains('unique order_id,number', $revisions);
    }

    #[Test]
    public function the_default_set_is_grouped_coloured_and_marks_what_it_should(): void
    {
        $set = PublicStatus::query()->orderBy('position')->with('statuses')->get()->mapWithKeys(
            static fn (PublicStatus $public): array => [$public->code => $public->statuses->map(
                static fn (OrderStatus $status): array => [
                    $status->getTranslation('name', 'en'),
                    $status->color->value,
                    $status->sets_milestone?->value,
                ],
            )->all()],
        )->all();

        $this->assertSame([
            'accepted' => [['New', 'info', null], ['Awaiting payment', 'warning', null]],
            'in_progress' => [['Taken into work', 'accent', null], ['Waiting for a call', 'warning', null], ['Being packed', 'accent', null]],
            'shipped' => [['Handed to delivery', 'info', null]],
            'completed' => [['Completed', 'success', 'received']],
            'cancelled' => [['Cancelled', 'neutral', 'cancelled']],
        ], $set);

        // Only what a status may set (§3): never placed, awaiting payment or refunded.
        foreach (OrderStatus::query()->whereNotNull('sets_milestone')->get() as $status) {
            $this->assertInstanceOf(Milestone::class, $status->sets_milestone);
            $this->assertTrue($status->sets_milestone->settableByStatus());
        }

        $this->assertContainsOnlyInstancesOf(StatusColor::class, OrderStatus::query()->get()->pluck('color'));
    }

    #[Test]
    public function the_default_set_is_written_in_every_language_of_the_site(): void
    {
        $accepted = PublicStatus::query()->where('code', 'accepted')->firstOrFail();
        $new = OrderStatus::query()->orderBy('position')->firstOrFail();

        $this->assertSame(['en', 'ru', 'kk'], array_keys($accepted->getTranslations('name')));
        $this->assertSame('Принят', $accepted->getTranslation('name', 'ru'));
        $this->assertSame('Мы получили заказ и свяжемся с вами.', $accepted->getTranslation('description', 'ru'));
        $this->assertSame('Новый', $new->getTranslation('name', 'ru'));

        // A language the package does not ship words in reads the fallback's, not a key.
        $this->assertSame('Accepted', $accepted->getTranslation('name', 'kk'));
        $this->assertSame('New', $new->getTranslation('name', 'kk'));

        foreach ([...PublicStatus::query()->get(), ...OrderStatus::query()->get()] as $status) {
            foreach ($status->getTranslations('name') as $locale => $name) {
                $this->assertStringNotContainsString('webx-orders::', $name, "[{$locale}] is a key, not a word");
            }
        }
    }
}
