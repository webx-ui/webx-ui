<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Notifications;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Compositions\CompositionsServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Notifications\Jobs\Deliver;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationsServiceProvider;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Notifications\OrderEvents;
use WebxUi\Orders\OrderDetails;
use WebxUi\Orders\Orders;
use WebxUi\Orders\OrderSettings;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Orders\Tests\Mcp\McpTestCase;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * A message about an order sent again by hand (decision 99): a row of «Notifications» repeated —
 * sent or failed alike — and «Send the buyer a letter» for an event the order has a reason for.
 * Each is a new row of the log marked manual with who asked, and a line of the order's history;
 * a second press sends nothing; without the right, or without the module, there is no action.
 *
 * The queue is faked: a row of the log and its job are what a send is here.
 */
final class ResendTest extends McpTestCase
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
            ThemeServiceProvider::class,
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
    public function a_letter_that_went_goes_again_as_a_row_of_its_own_with_who_sent_it(): void
    {
        $manager = $this->signIn();
        $order = $this->order();
        $letter = $this->accepted($order);

        $card = $this->getJson($this->api($order->id))->assertOk()->json('data.order');
        $this->assertTrue($card['actions']['resend']);
        $this->assertTrue($this->pickDelivery($card['notifications'], $letter->id)['resendable']);
        $this->assertSame('Order: placed', $this->pickDelivery($card['notifications'], $letter->id)['event_label']);

        $answer = $this->postJson($this->api($order->id.'/deliveries/'.$letter->id.'/resend'))->assertOk()->json('data.order');

        $again = Delivery::query()->where('resent_from', $letter->id)->sole();
        $this->assertSame([Delivery::QUEUED, 'alex@example.test', $letter->channel_id, true, $manager->id], [
            $again->status, $again->recipient, $again->channel_id, $again->manual, $again->manual_by,
        ]);
        $this->assertSame($letter->variables, $again->variables);
        // The row that went stays as it was.
        $this->assertSame(Delivery::SENT, $letter->refresh()->status);
        Bus::assertDispatched(Deliver::class, static fn (Deliver $job): bool => $job->deliveries === [(int) $again->id]);

        // The card's log names it, and the history says it with who did it.
        $row = $this->pickDelivery($answer['notifications'], $again->id);
        $this->assertSame([true, $manager->name], [$row['manual'], $row['manual_by']]);

        $line = self::pick($answer['timeline'], 'kind', StatusChange::NOTIFICATION_SENT);
        $this->assertSame(['orders.placed', 'Order: placed', 'alex@example.test', $manager->name], [
            $line['event'], $line['event_label'], $line['note'], $line['admin'],
        ]);

        // The notifications module's own log marks it too.
        $this->actingAs($this->manager(['notifications.view']), 'cms');
        $logged = collect($this->getJson('/api/cms/notifications/deliveries')->assertOk()->json('data'))->firstWhere('id', $again->id);
        $this->assertSame([true, $manager->name, $letter->id], [$logged['manual'], $logged['manual_by_name'], $logged['resent_from']]);
    }

    #[Test]
    public function a_second_press_sends_nothing(): void
    {
        $this->signIn();
        $order = $this->order();
        $letter = $this->accepted($order);
        $url = $this->api($order->id.'/deliveries/'.$letter->id.'/resend');

        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertStatus(422)->assertJsonPath('errors.delivery.0', __('webx-notifications::errors.resent-recently'));

        $this->assertSame(1, Delivery::query()->where('resent_from', $letter->id)->count());
        $this->assertSame(1, StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::NOTIFICATION_SENT)->count());
        Bus::assertDispatchedTimes(Deliver::class, 2);

        // The row the first press made takes the top of the log: a press on it is the same letter again.
        $again = Delivery::query()->where('resent_from', $letter->id)->sole();
        $again->forceFill(['status' => Delivery::SENT])->save();
        $this->postJson($this->api($order->id.'/deliveries/'.$again->id.'/resend'))->assertStatus(422);
        // And so is the event sent to the buyer by hand.
        $this->postJson($this->api($order->id.'/notify'), ['event' => OrderEvents::PLACED])->assertStatus(422);
        $this->assertSame(1, Delivery::query()->where('manual', true)->count());

        // A minute later it is a decision of its own, not a slip of the finger.
        Carbon::setTestNow(Carbon::now()->addSeconds(Delivery::RESEND_PAUSE + 1));
        $this->postJson($url)->assertOk();
        $this->assertSame(2, Delivery::query()->where('resent_from', $letter->id)->count());
        Carbon::setTestNow();
    }

    #[Test]
    public function the_buyer_is_told_of_an_event_again_to_the_e_mail_of_the_order_now(): void
    {
        $manager = $this->signIn();
        $order = $this->order();
        $this->accepted($order);
        $this->app->make(Orders::class)->updateDetails($order, (new OrderDetails)->customer(new Contact('Alex', 'Doe', null, 'alex-new@example.test')), 'panel');

        $meta = $this->getJson($this->api($order->id.'/notify'))->assertOk()->json('data');
        $this->assertSame([null, 'alex-new@example.test'], [$meta['reason'], $meta['email']]);
        $placed = self::pick($meta['events'], 'key', OrderEvents::PLACED);
        $this->assertTrue($placed['available']);
        $this->assertCount(1, $placed['channels']);
        // Not paid: the letter of the payment has no reason yet.
        $this->assertSame('no_basis', self::pick($meta['events'], 'key', OrderEvents::PAID)['reason']);

        $preview = $this->postJson($this->api($order->id.'/notify'), ['event' => OrderEvents::PLACED, 'dry_run' => true])->assertOk()->json('data');
        $this->assertSame('alex-new@example.test', $preview['previews'][0]['recipient']);
        $this->assertSame('Order '.$order->number.' accepted', $preview['previews'][0]['subject']);
        $this->assertStringContainsString($order->number, $preview['previews'][0]['plain']);
        $this->assertSame(0, Delivery::query()->where('manual', true)->count());

        $this->postJson($this->api($order->id.'/notify'), ['event' => OrderEvents::PLACED])->assertOk();

        // The buyer alone: the shop's recipients on the same event are not written to again.
        $sent = Delivery::query()->where('manual', true)->get();
        $this->assertSame([['alex-new@example.test', OrderEvents::PLACED, $manager->id, null]], $sent->map(static fn (Delivery $row): array => [
            $row->recipient, $row->event, $row->manual_by, $row->resent_from,
        ])->all());

        $this->postJson($this->api($order->id.'/notify'), ['event' => OrderEvents::PLACED])
            ->assertStatus(422)
            ->assertJsonPath('errors.event.0', __('webx-notifications::errors.resent-recently'));
        $this->assertSame(1, Delivery::query()->where('manual', true)->count());

        $this->postJson($this->api($order->id.'/notify'), ['event' => OrderEvents::PAID])
            ->assertStatus(422)
            ->assertJsonPath('errors.event.0', __('webx-orders::panel.notify-refusals.no_basis'));
    }

    #[Test]
    public function with_no_channel_to_the_buyer_the_action_is_there_and_says_why_not(): void
    {
        $this->signIn();
        $order = $this->order();
        Channel::query()->update(['enabled' => false]);

        $actions = $this->getJson($this->api($order->id))->assertOk()->json('data.order.actions');
        $this->assertTrue($actions['notify']);
        $this->assertSame(__('webx-orders::panel.notify-refusals.no_channel'), $actions['notify_reason']);

        $this->postJson($this->api($order->id.'/notify'), ['event' => OrderEvents::PLACED])
            ->assertStatus(422)
            ->assertJsonPath('errors.event.0', __('webx-orders::panel.notify-refusals.no_channel'));
    }

    #[Test]
    public function without_the_right_there_is_no_action_and_the_routes_refuse(): void
    {
        $order = $this->order();
        $letter = $this->accepted($order);
        $this->signIn(['orders.view']);

        $actions = $this->getJson($this->api($order->id))->assertOk()->json('data.order.actions');
        $this->assertSame([false, false], [$actions['resend'], $actions['notify']]);

        $this->postJson($this->api($order->id.'/deliveries/'.$letter->id.'/resend'))->assertForbidden();
        $this->postJson($this->api($order->id.'/notify'), ['event' => OrderEvents::PLACED])->assertForbidden();
        $this->getJson($this->api($order->id.'/notify'))->assertForbidden();
        $this->assertSame(0, Delivery::query()->where('manual', true)->count());
    }

    #[Test]
    public function a_row_of_another_order_or_one_that_had_nobody_to_go_to_is_refused(): void
    {
        $this->signIn();
        $order = $this->order();
        $other = $this->order();
        $theirs = $this->accepted($other);

        $this->postJson($this->api($order->id.'/deliveries/'.$theirs->id.'/resend'))
            ->assertStatus(422)
            ->assertJsonPath('errors.delivery.0', __('webx-orders::panel.notify-refusals.foreign'));

        $skipped = $this->accepted($order);
        $skipped->forceFill(['status' => Delivery::SKIPPED, 'recipient' => '', 'reason' => 'no_address'])->save();

        $this->postJson($this->api($order->id.'/deliveries/'.$skipped->id.'/resend'))
            ->assertStatus(422)
            ->assertJsonPath('errors.delivery.0', __('webx-notifications::errors.not-resendable', ['id' => $skipped->id]));
        $this->assertSame(0, Delivery::query()->where('manual', true)->count());
    }

    #[Test]
    public function the_agent_sends_again_with_a_dry_run_first(): void
    {
        $order = $this->order();
        $letter = $this->accepted($order);

        $dry = $this->ask('orders_notify', ['order' => $order->number, 'delivery' => $letter->id, 'dry_run' => true]);
        $this->assertSame([true, $letter->id, 'alex@example.test', null], [$dry['dry_run'], $dry['resent_from'], $dry['deliveries'][0]['recipient'], $dry['deliveries'][0]['id']]);
        $this->assertSame(0, Delivery::query()->where('manual', true)->count());

        $preview = $this->ask('orders_notify', ['order' => $order->number, 'event' => OrderEvents::PLACED, 'dry_run' => true]);
        $this->assertSame('Order '.$order->number.' accepted', $preview['previews'][0]['subject']);

        $sent = $this->ask('orders_notify', ['order' => $order->number, 'delivery' => $letter->id]);
        $this->assertFalse($sent['dry_run']);
        $this->assertSame($this->caller()->id, Delivery::query()->where('resent_from', $letter->id)->sole()->manual_by);

        $this->agent('orders_notify', ['order' => $order->number, 'delivery' => $letter->id])->assertHasErrors([__('webx-notifications::errors.resent-recently')]);

        $history = $this->ask('orders_history', ['order' => $order->number]);
        $this->assertSame('mcp', self::pick($history['timeline'], 'kind', StatusChange::NOTIFICATION_SENT)['source']);
    }

    /**
     * The letter «Order accepted» of an order, as the default channel to the buyer wrote it, made sent.
     */
    private function accepted(Order $order): Delivery
    {
        $channels = $this->app->make(OrderSettings::class)->get('orders.notify.placed');
        $buyer = Channel::query()->whereKey($channels)->get()->first(static fn (Channel $channel): bool => $channel->recipientList() === [['variable' => 'customer_email']])
            ?? $this->fail('No channel of «placed» writes to the buyer.');

        $row = Delivery::query()->where('subject_id', $order->id)->where('channel_id', $buyer->id)->latest('id')->first()
            ?? $this->fail('The order wrote no letter to the buyer.');
        $row->forceFill(['status' => Delivery::SENT, 'sent_at' => Carbon::now()])->save();

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function pickDelivery(array $rows, int $id): array
    {
        return self::pick($rows, 'id', $id);
    }
}
