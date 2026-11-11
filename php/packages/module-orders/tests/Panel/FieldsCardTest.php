<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cache\CacheGroups;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Events\OrderDetailsChanged;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderField;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderFields;

/**
 * The card «Additional» of an order and the manager's edit of it (WEBX_UI_ORDER_FIELDS.md §5.4,
 * §6): the snapshot's rows and the fields switched on with no answer, `PUT {id}/details` with
 * `fields`, the timeline and the journal, a field that is gone, and the reader without the right.
 */
final class FieldsCardTest extends PanelTestCase
{
    private const array INSTALLATION = ['key' => 'installation', 'type' => 'checkbox', 'label' => 'Installation needed', 'value' => true, 'text' => 'Yes'];

    private const array GONE = ['key' => 'heard_from', 'type' => 'text', 'label' => 'How did you hear about us?', 'value' => 'From a friend', 'text' => 'From a friend'];

    private const array GONE_LIST = ['key' => 'gift_wrap', 'type' => 'select', 'label' => 'Gift wrap', 'value' => 'red', 'text' => 'Red paper'];

    protected function setUp(): void
    {
        parent::setUp();

        $fields = $this->app->make(OrderFields::class);
        $fields->write(['key' => 'installation', 'type' => 'checkbox', 'label' => ['en' => 'Installation needed']]);
        $fields->write(['key' => 'for_company', 'type' => 'checkbox', 'place' => FieldPlaces::CONTACTS, 'label' => ['en' => 'For a company']]);
        $fields->write(['key' => 'company_name', 'type' => 'text', 'place' => FieldPlaces::CONTACTS, 'label' => ['en' => 'Company name'], 'required' => true, 'parent' => 'for_company']);
        $fields->write(['key' => 'delivery_time', 'type' => 'select', 'label' => ['en' => 'Delivery time'], 'options' => [
            ['value' => 'morning', 'label' => ['en' => '9:00–12:00']],
            ['value' => 'evening', 'label' => ['en' => '18:00–21:00']],
        ]]);
        $fields->write(['key' => 'directions', 'type' => 'textarea', 'label' => ['en' => 'Directions']]);
        $fields->write(['key' => 'retired', 'type' => 'text', 'label' => ['en' => 'Switched off'], 'active' => false]);
    }

    #[Test]
    public function the_card_has_the_snapshot_s_rows_then_the_fields_with_no_answer(): void
    {
        $order = $this->order(['fields' => [self::INSTALLATION, self::GONE, self::GONE_LIST]]);
        $this->signIn();

        $rows = $this->getJson($this->api($order->id))->assertOk()->json('data.order.fields');

        $this->assertSame(['installation', 'heard_from', 'gift_wrap', 'for_company', 'company_name', 'delivery_time', 'directions'], array_column($rows, 'key'));
        $this->assertSame([true, false, false, true], array_column(array_slice($rows, 0, 4), 'known'));
        $this->assertSame([null, null], [$rows[3]['value'], $rows[3]['text']]);
        // A list offers its options now; a list that is gone, the option the order holds.
        $this->assertSame([['value' => 'morning', 'label' => '9:00–12:00'], ['value' => 'evening', 'label' => '18:00–21:00']], $rows[5]['options']);
        $this->assertSame([['value' => 'red', 'label' => 'Red paper']], $rows[2]['options']);

        // The opened row of the list: the snapshot alone.
        $glance = $this->getJson($this->api($order->id).'/glance')->assertOk()->json('data.fields');
        $this->assertSame([
            ['key' => 'installation', 'label' => 'Installation needed', 'text' => 'Yes'],
            ['key' => 'heard_from', 'label' => 'How did you hear about us?', 'text' => 'From a friend'],
            ['key' => 'gift_wrap', 'label' => 'Gift wrap', 'text' => 'Red paper'],
        ], $glance);
    }

    #[Test]
    public function an_order_without_a_snapshot_and_a_site_without_fields_have_no_rows(): void
    {
        $order = $this->order();
        $this->signIn();
        // Straight in the table, as a site that never had fields: the fields behind a checkbox first.
        OrderField::query()->whereNotNull('parent_id')->delete();
        OrderField::query()->delete();
        $this->app->make(CacheGroups::class)->bump(OrderFields::GROUP);

        $this->getJson($this->api($order->id))->assertOk()->assertJsonPath('data.order.fields', []);
        $this->getJson($this->api($order->id).'/glance')->assertOk()->assertJsonPath('data.fields', []);
    }

    #[Test]
    public function the_manager_s_answers_change_the_rows_named_and_go_to_the_timeline_and_the_journal(): void
    {
        $order = $this->order(['fields' => [self::INSTALLATION, self::GONE]]);
        $this->signIn();
        $events = [];
        $this->app['events']->listen(OrderDetailsChanged::class, static function (OrderDetailsChanged $event) use (&$events): void {
            $events[] = $event;
        });

        $answer = $this->putJson($this->api($order->id).'/details', ['fields' => [
            'installation' => false,
            'heard_from' => ' From an advert ',
            'delivery_time' => 'evening',
            // Behind an unticked checkbox and required at the checkout: the manager writes it all the same.
            'company_name' => 'Company A',
        ]])->assertOk();

        $expected = [
            ['key' => 'heard_from', 'type' => 'text', 'label' => 'How did you hear about us?', 'value' => 'From an advert', 'text' => 'From an advert'],
            ['key' => 'delivery_time', 'type' => 'select', 'label' => 'Delivery time', 'value' => 'evening', 'text' => '18:00–21:00'],
            ['key' => 'company_name', 'type' => 'text', 'label' => 'Company name', 'value' => 'Company A', 'text' => 'Company A'],
        ];
        $this->assertSame($expected, Order::query()->findOrFail($order->id)->fields);
        $this->assertSame(['heard_from', 'delivery_time', 'company_name'], array_slice(array_column($answer->json('data.order.fields'), 'key'), 0, 3));

        $this->assertCount(1, $events);
        $this->assertSame(['fields'], $events[0]->fields);
        $this->assertSame(['fields' => [self::INSTALLATION, self::GONE]], $events[0]->before);
        $this->assertSame($expected, $events[0]->order->fields);

        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::DETAILS)->sole();
        $this->assertSame('fields', $line->note);
        $this->assertContains('Additional fields', array_column($answer->json('data.order.timeline'), 'note'));

        $journal = DB::table('cms_history')->where('subject_type', 'orders.order')->where('subject_id', $order->id)->sole();
        $change = array_column(json_decode((string) $journal->changes, true), null, 'field')['fields'];
        $this->assertSame('Installation needed: Yes; How did you hear about us?: From a friend', $change['from']);
        $this->assertSame('How did you hear about us?: From an advert; Delivery time: 18:00–21:00; Company name: Company A', $change['to']);

        // The same again changes nothing: silence.
        $this->putJson($this->api($order->id).'/details', ['fields' => ['delivery_time' => 'evening', 'installation' => null]])->assertOk();
        $this->assertCount(1, $events);
    }

    #[Test]
    public function an_answer_as_it_was_keeps_the_snapshot_s_words_a_new_one_takes_the_definition_s(): void
    {
        $order = $this->order(['fields' => [
            ['key' => 'delivery_time', 'type' => 'select', 'label' => 'Time (old words)', 'value' => 'morning', 'text' => 'Mornings (old words)'],
            ['key' => 'directions', 'type' => 'textarea', 'label' => 'Directions (old words)', 'value' => "Gate 2\nFloor 3", 'text' => "Gate 2\nFloor 3"],
        ]]);
        $this->signIn();

        $this->putJson($this->api($order->id).'/details', ['fields' => ['delivery_time' => 'morning', 'directions' => "Gate 2\r\nFloor 4"]])->assertOk();

        $this->assertSame([
            ['key' => 'delivery_time', 'type' => 'select', 'label' => 'Time (old words)', 'value' => 'morning', 'text' => 'Mornings (old words)'],
            ['key' => 'directions', 'type' => 'textarea', 'label' => 'Directions', 'value' => "Gate 2\nFloor 4", 'text' => "Gate 2\nFloor 4"],
        ], Order::query()->findOrFail($order->id)->fields);
    }

    #[Test]
    public function the_answers_are_checked_by_the_type_the_length_and_the_option_under_their_fields(): void
    {
        $order = $this->order(['fields' => [self::GONE_LIST]]);
        $this->signIn();

        $this->putJson($this->api($order->id).'/details', ['fields' => [
            'delivery_time' => 'night',
            'company_name' => str_repeat('a', 191),
            'directions' => ['not', 'a', 'text'],
            'nobody' => 'x',
            'retired' => 'x',
            // A list that is gone takes no other option.
            'gift_wrap' => 'blue',
        ]])->assertStatus(422)->assertJsonValidationErrors([
            'fields.delivery_time', 'fields.company_name', 'fields.directions', 'fields.nobody', 'fields.retired', 'fields.gift_wrap',
        ]);
        $this->assertSame([self::GONE_LIST], Order::query()->findOrFail($order->id)->fields);
        $this->assertFalse(StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::DETAILS)->exists());

        // But it may go, and the order is left with no rows at all.
        $this->putJson($this->api($order->id).'/details', ['fields' => ['gift_wrap' => '']])->assertOk()->assertJsonPath('data.order.fields.0.key', 'installation');
        $this->assertNull(Order::query()->findOrFail($order->id)->fields);
    }

    #[Test]
    public function whoever_only_views_orders_reads_the_rows_and_may_not_change_them(): void
    {
        $order = $this->order(['fields' => [self::INSTALLATION]]);
        $this->signIn(['orders.view']);

        $card = $this->getJson($this->api($order->id))->assertOk();
        $this->assertSame('installation', $card->json('data.order.fields.0.key'));
        $this->assertFalse($card->json('data.order.actions.details'));

        $this->putJson($this->api($order->id).'/details', ['fields' => ['installation' => null]])->assertForbidden();
        $this->assertSame([self::INSTALLATION], Order::query()->findOrFail($order->id)->fields);
    }
}
