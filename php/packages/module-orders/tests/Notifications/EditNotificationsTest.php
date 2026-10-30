<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Notifications;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Cart\Money;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\NotificationsServiceProvider;
use WebxUi\Orders\ItemsEdit;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Notifications\DefaultChannels;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\Notifications\OrderEvents;
use WebxUi\Orders\NotifyRefused;
use WebxUi\Orders\Orders;
use WebxUi\Orders\OrderSettings;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Orders\Tests\PlaceTestCase;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The buyer told of an edit (spec §40.2): «Order changed» when the manager left «Tell the buyer»
 * on, never the edit's reason; «Payment due» by hand while the order owes — greyed with the reason
 * once it owes nothing.
 *
 * The queue is faked: a row of the log is what a send is here; the letter itself is rendered from
 * the row's values through its channel, as the job would.
 */
final class EditNotificationsTest extends PlaceTestCase
{
    private const string REASON = 'Supplier ran out — internal note';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            CompositionsServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            McpServiceProvider::class,
            SettingsServiceProvider::class,
            NotificationsServiceProvider::class,
            WidgetsServiceProvider::class,
            CartServiceProvider::class,
            OrdersServiceProvider::class,
        ];
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'https://site.test');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('queue.default', 'database');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    #[Test]
    public function an_edit_tells_the_buyer_what_changed_and_never_why(): void
    {
        $order = $this->paidOrder();
        $line = $this->line($order);

        $this->orders()->editItems($order, new ItemsEdit(
            revision: 0,
            qty: [$line->id => 3],
            reason: self::REASON,
            message: 'We added two more, as agreed on the phone.',
            notify: true,
        ));

        $rows = $this->rows($order, OrderEvents::ITEMS_CHANGED);
        $this->assertCount(1, $rows);
        $this->assertSame('alex@example.test', $rows[0]->recipient);

        $values = $rows[0]->variables ?? [];
        $this->assertArrayNotHasKey('reason', $values);
        $this->assertSame([['name' => 'Item A', 'options' => '', 'qty_from' => '1', 'qty_to' => '3', 'added' => '', 'removed' => '']], $values['changes']);
        $this->assertSame('3', $values['items'][0]['qty']);
        $this->assertSame('due', $values['balance_kind']);
        $this->assertNotSame('', $values['amount_due']);
        $this->assertSame('', $values['amount_over']);
        $this->assertNotSame($values['total'], $values['previous_total']);

        // The letter as the job renders it: the words to the buyer are there, the reason nowhere.
        $letter = $this->app->make(Notifications::class)->render(Channel::query()->findOrFail($rows[0]->channel_id), $values, 'en');
        $this->assertSame('Order '.$order->number.' changed', $letter['subject']);
        $this->assertStringContainsString('We added two more, as agreed on the phone.', $letter['body']);
        $this->assertStringContainsString('1 → 3', $letter['body']);
        $this->assertStringContainsString((string) $values['amount_due'], $letter['body']);
        $this->assertStringNotContainsString(self::REASON, $letter['body'].$letter['plain'].$letter['subject']);
        $this->assertStringNotContainsString(self::REASON, (string) json_encode($values));
    }

    #[Test]
    public function an_edit_with_the_flag_off_tells_nobody(): void
    {
        $order = $this->paidOrder();

        $this->orders()->editItems($order, new ItemsEdit(revision: 0, qty: [$this->line($order)->id => 2], reason: self::REASON, notify: false));

        $this->assertSame(1, $order->refresh()->items_revision);
        $this->assertSame([], $this->rows($order, OrderEvents::ITEMS_CHANGED));

        // A dry run tells nobody either, flag or not.
        $this->orders()->editItems($order, new ItemsEdit(revision: 1, qty: [$this->line($order)->id => 3], notify: true), dryRun: true);
        $this->assertSame(0, Delivery::query()->where('event', OrderEvents::ITEMS_CHANGED)->count());
    }

    #[Test]
    public function an_edit_down_tells_the_buyer_what_comes_back(): void
    {
        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 3]]));
        $this->orders()->mark($order, Milestone::Paid, 'panel');

        $this->orders()->editItems($order->refresh(), new ItemsEdit(revision: 0, qty: [$this->line($order)->id => 1]));

        $values = $this->rows($order, OrderEvents::ITEMS_CHANGED)[0]->variables ?? [];
        $this->assertSame(['over', ''], [$values['balance_kind'], $values['amount_due']]);
        $this->assertSame($values['balance'], $values['amount_over']);
    }

    #[Test]
    public function payment_requested_is_offered_while_the_order_owes_and_greyed_once_it_does_not(): void
    {
        $order = $this->place($this->cart());
        $channels = $this->app->make(OrderChannels::class);

        // Not paid yet: «pay for the order», the whole sum.
        $event = $this->event($channels->buyerEvents($order), OrderEvents::PAYMENT_REQUESTED);
        $this->assertSame([true, true, null], [$event['basis'], $event['available'], $event['reason']]);

        $preview = $this->orders()->notify($order, ['event' => OrderEvents::PAYMENT_REQUESTED], 'panel', dryRun: true);
        $this->assertSame('Order '.$order->number.': '.$this->money($order->payable, $order->currency).' to pay', $preview->previews[0]['subject']);
        $this->assertSame([], $this->rows($order, OrderEvents::PAYMENT_REQUESTED));

        // Paid in full: nothing to ask for — in the list, greyed with why.
        $this->orders()->mark($order, Milestone::Paid, 'panel');
        $event = $this->event($channels->buyerEvents($order->refresh()), OrderEvents::PAYMENT_REQUESTED);
        $this->assertSame([false, false, NotifyRefused::NO_BASIS], [$event['basis'], $event['available'], $event['reason']]);
        $this->assertSame('The order owes nothing: there is no payment to ask for.', NotifyRefused::label($event['reason'], $event['key']));

        try {
            $this->orders()->notify($order, ['event' => OrderEvents::PAYMENT_REQUESTED], 'panel');
            $this->fail('Nothing is owed: nothing to ask for.');
        } catch (NotifyRefused $refused) {
            $this->assertSame('The order owes nothing: there is no payment to ask for.', $refused->said());
        }

        // An edit up makes it owe again: the letter goes, with what is left to pay.
        $this->orders()->editItems($order, new ItemsEdit(revision: 0, qty: [$this->line($order)->id => 2], notify: false));
        $this->orders()->notify($order->refresh(), ['event' => OrderEvents::PAYMENT_REQUESTED], 'panel');

        $rows = $this->rows($order, OrderEvents::PAYMENT_REQUESTED);
        $this->assertCount(1, $rows);
        $this->assertSame($this->money($order->balance, $order->currency), $rows[0]->variables['amount_due'] ?? null);
    }

    #[Test]
    public function a_site_with_its_channels_set_up_before_the_edits_gets_their_two(): void
    {
        $settings = $this->app->make(OrderSettings::class);
        $before = [$settings->get('orders.notify.placed'), $settings->get('orders.notify.paid')];

        $this->app->make(Settings::class)->clear([OrderEvents::SETTINGS[OrderEvents::ITEMS_CHANGED], OrderEvents::SETTINGS[OrderEvents::PAYMENT_REQUESTED]]);
        Channel::query()->whereIn('event', [OrderEvents::ITEMS_CHANGED, OrderEvents::PAYMENT_REQUESTED])->forceDelete();

        $made = $this->app->make(DefaultChannels::class)->install();

        $this->assertSame([OrderEvents::ITEMS_CHANGED, OrderEvents::PAYMENT_REQUESTED], array_map(static fn (Channel $one): string => $one->event, $made));
        $this->assertSame($before, [$settings->get('orders.notify.placed'), $settings->get('orders.notify.paid')]);

        // A site that switched them off has chosen: nothing comes back.
        $settings->store([OrderEvents::SETTINGS[OrderEvents::ITEMS_CHANGED] => []]);
        $this->app->make(Settings::class)->clear([OrderEvents::SETTINGS[OrderEvents::PAYMENT_REQUESTED]]);
        $this->assertSame([], $this->app->make(DefaultChannels::class)->install());
    }

    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    private function paidOrder(): Order
    {
        $order = $this->place($this->cart());
        $this->orders()->mark($order, Milestone::Paid, 'panel');

        return $order->refresh();
    }

    private function line(Order $order): OrderItem
    {
        return OrderItem::query()->where('order_id', $order->id)->whereNull('parent_id')->orderBy('id')->firstOrFail();
    }

    private function money(int $minor, string $currency): string
    {
        return $this->app->make(Money::class)->format($minor, $currency);
    }

    /**
     * @param  array{events: list<array<string, mixed>>}  $meta
     * @return array<string, mixed>
     */
    private function event(array $meta, string $key): array
    {
        foreach ($meta['events'] as $event) {
            if ($event['key'] === $key) {
                return $event;
            }
        }

        $this->fail("No {$key}.");
    }

    /**
     * @return list<Delivery>
     */
    private function rows(Order $order, string $event): array
    {
        return array_values(Delivery::query()->where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)->where('event', $event)->orderBy('id')->get()->all());
    }
}
