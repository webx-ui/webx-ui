<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Notifications;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\NotificationsServiceProvider;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Doctor\OrdersNotificationsCheck;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Notifications\DefaultChannels;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\Notifications\OrderEvents;
use WebxUi\Orders\Notifications\OrderValues;
use WebxUi\Orders\Orders;
use WebxUi\Orders\OrderSettings;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Orders\OrderStatuses;
use WebxUi\Orders\Panel\SettingsScreen;
use WebxUi\Orders\Panel\StatusBoard;
use WebxUi\Orders\Tests\PlaceTestCase;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The order with `webx-ui/module-notifications` and `module-settings` (spec §10): the events, the
 * set of channels a site starts with, which move tells whom, and what the doctor says.
 *
 * The queue is faked: a row of the log is written when `send()` is called, and that is what the
 * stage checks — one row per channel and recipient, none for a move inside a group.
 */
final class NotificationsTest extends PlaceTestCase
{
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
    public function the_events_are_declared_with_their_values(): void
    {
        $events = $this->app->make(NotificationEvents::class);

        foreach (OrderEvents::keys() as $key) {
            $definition = $events->find($key) ?? $this->fail($key);

            $this->assertSame('email', $definition->variable('customer_email')?->address);
            $this->assertTrue($definition->variable('status')->internal ?? false);
        }

        $this->assertNotNull($events->find(OrderEvents::CANCELLED)?->variable('reason'));
        $this->assertNotNull($events->find(OrderEvents::REFUNDED)?->variable('refund_amount'));
        $this->assertNull($events->find(OrderEvents::PLACED)?->variable('reason'));
    }

    #[Test]
    public function every_default_template_saves_as_a_channel_in_every_language(): void
    {
        $form = $this->app->make(ChannelForm::class);
        $events = $this->app->make(NotificationEvents::class);

        foreach (OrderEvents::keys() as $key) {
            foreach (['email', 'telegram'] as $provider) {
                $sample = $form->sample($events->find($key) ?? $this->fail($key), $provider);

                $this->assertNotNull($sample, "{$key} {$provider}");
                // The site speaks en, ru and kk: the package has words in two of them.
                $this->assertSame(['en', 'ru'], array_keys($sample['template']), "{$key} {$provider}");

                $saved = $form->save(new Channel, [
                    'name' => "{$key} {$provider}",
                    'provider' => $provider,
                    'event' => $key,
                    'recipients' => [],
                ]);

                $this->assertSame($sample['template'], $saved['channel']->getTranslations('template'));
            }
        }
    }

    #[Test]
    public function a_site_starts_with_three_channels_and_the_shop_one_is_off_without_contacts(): void
    {
        $settings = $this->app->make(OrderSettings::class);
        $placed = $settings->get('orders.notify.placed');
        $paid = $settings->get('orders.notify.paid');

        $this->assertCount(2, $placed);
        $this->assertCount(1, $paid);

        [$customer, $admin] = Channel::query()->whereKey($placed)->orderBy('id')->get()->all();
        $payment = Channel::query()->findOrFail($paid[0]);

        $this->assertSame([OrderEvents::PLACED, 'email', true], [$customer->event, $customer->provider, $customer->enabled]);
        $this->assertSame([['variable' => 'customer_email']], $customer->recipientList());
        $this->assertSame('Order {{number}} accepted', $customer->subjectIn('en'));
        $this->assertSame('Заказ {{number}} принят', $customer->subjectIn('ru'));

        $this->assertSame([OrderEvents::PLACED, false, []], [$admin->event, $admin->enabled, $admin->recipientList()]);
        $this->assertSame('New order {{number}}', $admin->subjectIn('en'));

        $this->assertSame([OrderEvents::PAID, true], [$payment->event, $payment->enabled]);
        $this->assertSame([['variable' => 'customer_email']], $payment->recipientList());

        // Made once: a second run over a site that has them makes nothing.
        $this->assertSame([], $this->app->make(DefaultChannels::class)->install());

        $diagnoses = $this->app->make(OrdersNotificationsCheck::class)->run();

        $this->assertSame(['content'], array_map(static fn (Diagnosis $one): string => $one->level(), $diagnoses));
        $this->assertStringContainsString('has no recipient and is switched off', $diagnoses[0]->detail);
    }

    #[Test]
    public function a_new_order_writes_to_the_buyer_and_to_the_address_of_the_contacts(): void
    {
        $this->restartWithContacts('shop@example.com');

        $order = $this->place($this->cart());
        $rows = $this->rows($order);

        $this->assertSame([
            [OrderEvents::PLACED, 'alex@example.test'],
            [OrderEvents::PLACED, 'shop@example.com'],
        ], array_map(static fn (Delivery $row): array => [$row->event, $row->recipient], $rows));

        $values = $rows[0]->variables ?? [];
        $this->assertSame($order->number, $values['number']);
        $this->assertSame('https://site.test/cms/orders/'.$order->id, $values['order_url']);
        $this->assertSame('Item A', $values['items'][0]['name'] ?? null);
        $this->assertSame('New', $values['status']);
        $this->assertSame('Accepted', $values['public_status']);

        $this->assertSame([Diagnosis::OK], array_map(static fn (Diagnosis $one): string => $one->state, $this->app->make(OrdersNotificationsCheck::class)->run()));
    }

    #[Test]
    public function the_demo_orders_tell_nobody_and_the_next_real_one_does(): void
    {
        $this->restartWithContacts('shop@example.com');

        $this->artisan('webx:demo', ['--module' => 'orders'])->assertSuccessful();
        @unlink($this->app->make(DemoLedger::class)->path());

        $this->assertSame(12, Order::query()->count());
        $this->assertSame(0, Delivery::query()->count());

        $this->assertCount(2, $this->rows($this->place($this->cart())));
    }

    #[Test]
    public function a_move_inside_a_group_writes_nothing_and_entering_a_public_status_writes_a_row_per_channel(): void
    {
        $order = $this->place($this->cart());
        $before = Delivery::query()->count();

        $this->orders()->moveTo($order, $this->named('Awaiting payment'), 'panel');

        $this->assertSame($before, Delivery::query()->count());

        $one = $this->channel('Buyer, in progress', [['variable' => 'customer_email']]);
        $two = $this->channel('Shop, in progress', [['email' => 'shop@example.com']]);
        $group = PublicStatus::query()->where('code', 'in_progress')->firstOrFail();
        $group->options = [OrderChannels::OPTION => [$one->id, $two->id]];
        $group->save();

        $move = $this->orders()->moveTo($order, $this->named('Taken into work'), 'panel');
        $this->assertSame([$one->id, $two->id], $move->notifications);

        $rows = Delivery::query()->where('event', OrderEvents::PUBLIC_STATUS)->orderBy('id')->get();

        $this->assertSame([[$one->id, 'alex@example.test'], [$two->id, 'shop@example.com']], $rows->map(static fn (Delivery $row): array => [$row->channel_id, $row->recipient])->all());
        $this->assertSame('In progress', $rows[0]->variables['public_status'] ?? null);

        // Further inside the same group: still nobody.
        $this->orders()->moveTo($order->refresh(), $this->named('Waiting for a call'), 'panel');
        $this->assertSame(2, Delivery::query()->where('event', OrderEvents::PUBLIC_STATUS)->count());
    }

    #[Test]
    public function a_payment_tells_the_buyer(): void
    {
        $order = $this->place($this->cart());

        $this->orders()->mark($order, Milestone::Paid, 'panel', reference: 'TX-1');

        $rows = Delivery::query()->where('event', OrderEvents::PAID)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('alex@example.test', $rows[0]->recipient);
        $this->assertSame('TX-1', $rows[0]->variables['reference'] ?? null);
    }

    #[Test]
    public function a_channel_to_the_buyer_that_shows_the_internal_status_saves_with_a_warning(): void
    {
        $form = $this->app->make(ChannelForm::class);

        $toBuyer = $form->save(new Channel, [
            'name' => 'Buyer',
            'provider' => 'email',
            'event' => OrderEvents::PUBLIC_STATUS,
            'recipients' => [['variable' => 'customer_email']],
            'subject' => 'Order {{number}}',
            'template' => 'Now: {{status}}',
        ]);

        $this->assertCount(1, $toBuyer['warnings']);
        $this->assertStringContainsString('{{status}}', $toBuyer['warnings'][0]);

        $toShop = $form->save(new Channel, [
            'name' => 'Shop',
            'provider' => 'email',
            'event' => OrderEvents::PUBLIC_STATUS,
            'recipients' => [['email' => 'shop@example.com']],
            'subject' => 'Order {{number}}',
            'template' => 'Now: {{status}}',
        ]);

        $this->assertSame([], $toShop['warnings']);
    }

    #[Test]
    public function a_public_status_keeps_its_channels_and_a_deleted_one_tells_nobody(): void
    {
        $channel = $this->channel('Buyer, shipped', [['variable' => 'customer_email']]);
        $group = PublicStatus::query()->where('code', 'shipped')->firstOrFail();

        $this->app->make(OrderStatuses::class)->writePublic([
            'code' => 'shipped',
            'name' => $group->getTranslations('name'),
            'notify_channels' => [$channel->id, $channel->id, 999999],
        ], $group);

        $this->assertSame([$channel->id], OrderChannels::ofPublic($group->refresh()));

        $group->delete();

        $this->assertSame([], OrderChannels::ofPublic($group));
    }

    #[Test]
    public function the_values_of_an_order_are_strings_in_its_language(): void
    {
        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]), ['locale' => 'ru']);
        $values = $this->app->make(OrderValues::class)->of(OrderData::of($order));

        $this->assertSame('Товар A', $values['items'][0]['name']);
        $this->assertSame('2', $values['items'][0]['qty']);
        $this->assertSame('Принят', $values['public_status']);
        // The signed page of the order in its language (§13.1): the prefix is signed with the path.
        $this->assertStringStartsWith('http://localhost/ru/order/'.$order->number.'?signature=', $values['public_url']);
        $this->assertSame('', $values['discount']);
        $this->assertIsString($values['payable']);
    }

    #[Test]
    public function the_screens_offer_the_channels_and_keep_only_ones_that_exist(): void
    {
        $board = $this->app->make(StatusBoard::class)->describe('en');
        $this->assertTrue($board['notifications']);
        $this->assertSame([], $board['groups'][0]['channels']);

        $targets = array_column($this->app->make(SettingsScreen::class)->patch(), 'target');
        $this->assertNotContains('notify-card', $targets);

        $settings = $this->app->make(OrderSettings::class);
        $channel = $this->channel('Shop', [['email' => 'shop@example.com']]);

        try {
            $settings->store(['orders.notify.refunded' => [$channel->id, 'nobody']]);
            $this->fail('A list with a word in it is refused.');
        } catch (ValidationException $refused) {
            $this->assertArrayHasKey('orders.notify.refunded', $refused->errors());
        }

        $settings->store(['orders.notify.refunded' => [$channel->id, 999999]]);
        $this->assertSame([$channel->id], $settings->get('orders.notify.refunded'));

        // Saved empty is a choice: nobody is told.
        $settings->store(['orders.notify.paid' => []]);
        $this->assertSame([], $settings->get('orders.notify.paid'));
    }

    #[Test]
    public function the_card_lists_what_was_sent_about_the_order(): void
    {
        $order = $this->place($this->cart());
        $rows = $this->app->make(OrderChannels::class)->deliveries($order);

        // The buyer's letter; the shop's channel is off without contacts and writes a skipped row.
        $this->assertNotSame([], $rows);
        $this->assertContains('alex@example.test', array_column($rows, 'recipient'));
        $this->assertSame(OrderEvents::PLACED, $rows[0]['event']);
    }

    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    private function named(string $name): OrderStatus
    {
        return OrderStatus::query()->get()->firstOrFail(static fn (OrderStatus $status): bool => $status->getTranslation('name', 'en') === $name);
    }

    /**
     * @param  list<array<string, mixed>>  $recipients
     */
    private function channel(string $name, array $recipients): Channel
    {
        return $this->app->make(ChannelForm::class)->save(new Channel, [
            'name' => $name,
            'provider' => 'email',
            'event' => OrderEvents::PUBLIC_STATUS,
            'recipients' => $recipients,
            'enabled' => true,
        ])['channel'];
    }

    /**
     * The set made again on a site that has an e-mail in its contacts.
     */
    private function restartWithContacts(string $email): void
    {
        Channel::query()->withTrashed()->forceDelete();
        $settings = $this->app->make(Settings::class);
        $settings->clear(OrderSettings::NOTIFY);
        $settings->save(['contacts.emails' => [['email' => $email]]]);

        $this->assertCount(3, $this->app->make(DefaultChannels::class)->install());
    }

    /**
     * @return list<Delivery>
     */
    private function rows(Order $order): array
    {
        return array_values(Delivery::query()->where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)->orderBy('id')->get()->all());
    }
}
