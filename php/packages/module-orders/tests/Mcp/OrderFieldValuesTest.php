<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderFields;

/**
 * The answers to the extra fields for an agent (WEBX_UI_ORDER_FIELDS.md §8.2): `orders_get` gives the
 * snapshot whole, `orders_list` none of it; `orders_update` takes `fields` by the rules of the
 * panel's edit, its dry run answers the rows that would change and keeps nothing, a key misspelt
 * is a refusal; the history has the line `details`, as for the contacts.
 */
final class OrderFieldValuesTest extends McpTestCase
{
    private const array INSTALLATION = ['key' => 'installation', 'type' => 'checkbox', 'label' => 'Installation needed', 'value' => true, 'text' => 'Yes'];

    private const array GONE = ['key' => 'heard_from', 'type' => 'text', 'label' => 'How did you hear about us?', 'value' => 'From a friend', 'text' => 'From a friend'];

    protected function setUp(): void
    {
        parent::setUp();

        $fields = $this->app->make(OrderFields::class);
        $fields->write(['key' => 'installation', 'type' => 'checkbox', 'label' => ['en' => 'Installation needed']]);
        $fields->write(['key' => 'for_company', 'type' => 'checkbox', 'place' => FieldPlaces::CONTACTS, 'label' => ['en' => 'For a company']]);
        $fields->write(['key' => 'company_name', 'type' => 'text', 'label' => ['en' => 'Company name'], 'required' => true, 'parent' => 'for_company']);
        $fields->write(['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Delivery time'], 'options' => [
            ['value' => 'morning', 'label' => ['en' => '9:00–12:00']],
            ['value' => 'evening', 'label' => ['en' => '18:00–21:00']],
        ]]);
        $fields->write(['key' => 'retired', 'type' => 'text', 'label' => ['en' => 'Switched off'], 'active' => false]);
    }

    #[Test]
    public function the_order_gives_its_snapshot_whole_and_the_list_none_of_it(): void
    {
        $order = $this->order(['fields' => [self::INSTALLATION, self::GONE]]);
        $bare = $this->order();

        $this->assertSame([self::INSTALLATION, self::GONE], $this->ask('orders_get', ['order' => $order->number])['fields']);
        $this->assertSame([], $this->ask('orders_get', ['order' => $bare->number])['fields']);

        $list = $this->ask('orders_list');
        $this->assertSame(2, $list['total']);
        $this->assertStringNotContainsString('From a friend', (string) json_encode($list));
    }

    #[Test]
    public function the_dry_run_answers_the_rows_that_would_change_and_keeps_nothing(): void
    {
        $order = $this->order(['fields' => [self::INSTALLATION, self::GONE]]);
        $change = ['order' => $order->number, 'fields' => [
            'installation' => null,
            'delivery_time' => 'evening',
            // Behind an unticked checkbox and required at the checkout: the manager writes it all the same (§6.1).
            'company_name' => 'Company A',
        ]];

        $dry = $this->ask('orders_update', [...$change, 'dry_run' => true]);

        $this->assertTrue($dry['dry_run']);
        $this->assertSame(['fields'], $dry['changed']);
        $this->assertSame([
            ['key' => 'installation', 'label' => 'Installation needed', 'before' => 'Yes', 'after' => null],
            ['key' => 'delivery_time', 'label' => 'Delivery time', 'before' => null, 'after' => '18:00–21:00'],
            ['key' => 'company_name', 'label' => 'Company name', 'before' => null, 'after' => 'Company A'],
        ], $dry['fields_changed']);
        $this->assertSame(['heard_from', 'delivery_time', 'company_name'], array_column($dry['fields'], 'key'));

        // Rehearsed and rolled back: the snapshot, the history and the journal are as they were.
        $this->assertSame([self::INSTALLATION, self::GONE], Order::query()->findOrFail($order->id)->fields);
        $this->assertSame(0, StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::DETAILS)->count());
        $this->assertSame(0, DB::table('cms_history')->where('subject_type', 'orders.order')->where('subject_id', $order->id)->count());

        $done = $this->ask('orders_update', $change);

        $this->assertFalse($done['dry_run']);
        $this->assertSame($dry['fields_changed'], $done['fields_changed']);
        $expected = [
            self::GONE,
            ['key' => 'delivery_time', 'type' => 'select', 'label' => 'Delivery time', 'value' => 'evening', 'text' => '18:00–21:00'],
            ['key' => 'company_name', 'type' => 'text', 'label' => 'Company name', 'value' => 'Company A', 'text' => 'Company A'],
        ];
        $this->assertSame($expected, Order::query()->findOrFail($order->id)->fields);
        $this->assertSame($expected, $this->ask('orders_get', ['order' => $order->number])['fields']);

        // The history has the line of the details, as for the contacts; the journal, the rows.
        $timeline = $this->ask('orders_history', ['order' => $order->number])['timeline'];
        $this->assertContains('Additional fields', array_column($timeline, 'note'));
        $this->assertSame('fields', StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::DETAILS)->sole()->note);
        $this->assertSame(1, DB::table('cms_history')->where('subject_type', 'orders.order')->where('subject_id', $order->id)->count());

        // The same again changes nothing.
        $again = $this->ask('orders_update', $change);
        $this->assertSame([[], []], [$again['changed'], $again['fields_changed']]);

        // A field that is gone keeps its label; a text of it may change, alongside the contacts.
        $both = $this->ask('orders_update', ['order' => $order->number, 'manager_note' => 'VIP', 'fields' => ['heard_from' => 'From an advert']]);
        $this->assertSame(['manager_note', 'fields'], $both['changed']);
        $this->assertSame([['key' => 'heard_from', 'label' => 'How did you hear about us?', 'before' => 'From a friend', 'after' => 'From an advert']], $both['fields_changed']);
    }

    #[Test]
    public function a_key_misspelt_or_a_value_out_of_the_rules_is_a_refusal(): void
    {
        $order = $this->order(['fields' => [self::INSTALLATION]]);
        $refused = function (array $fields, string $error) use ($order): void {
            $this->agent('orders_update', ['order' => $order->number, 'fields' => $fields, 'dry_run' => true])->assertHasErrors([$error]);
            $this->agent('orders_update', ['order' => $order->number, 'fields' => $fields])->assertHasErrors([$error]);
            $this->assertSame([self::INSTALLATION], Order::query()->findOrFail($order->id)->fields, 'nothing written');
        };

        $refused(['compny' => 'Company A'], 'No field „compny“ on this order and no such definition: orders_checkout_fields_list lists them.');
        $refused(['retired' => 'Again'], 'The field „retired“ is switched off and this order has no answer to it');
        // The rules of the panel's edit, under the field's name.
        $refused(['delivery_time' => 'night'], 'fields.delivery_time');
        $refused(['installation' => false, 'company_name' => str_repeat('a', 191)], 'fields.company_name');

        $this->agent('orders_update', ['order' => $order->number, 'fields' => ['Company A']])->assertHasErrors(['`fields` is an object']);
        $this->agent('orders_update', ['order' => $order->number, 'fields' => []])->assertHasErrors(['`fields` is an object']);
        $this->agent('orders_update', ['order' => $order->number, 'fields' => ['installation' => null]], $this->manager(['orders.view']))
            ->assertHasErrors(['[orders.manage]']);
    }
}
