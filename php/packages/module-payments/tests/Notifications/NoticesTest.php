<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Notifications;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\NestedSet\NestedSetServiceProvider;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\NotificationsServiceProvider;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\Notifications\OrderEvents;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Notifications\PaymentNotices;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\Tests\Panel\PanelCase;
use WebxUi\Routing\RoutingServiceProvider;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;

/**
 * The letters (spec §11.5, §23 П7): the order's events carry the pay link, the bank details and
 * the method — in the channel's editor as in a message sent —, and a problem of a payment goes to
 * the channels chosen on the settings tab «Payments».
 */
final class NoticesTest extends PanelCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NestedSetServiceProvider::class,
            RoutingServiceProvider::class,
            ...parent::getPackageProviders($app),
            McpServiceProvider::class,
            SettingsServiceProvider::class,
            NotificationsServiceProvider::class,
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
    public function the_editor_of_a_channel_offers_the_payments_values_in_every_event_of_an_order(): void
    {
        $this->signIn(['notifications.view']);
        $events = array_column((array) $this->getJson('/api/cms/notifications/meta')->assertOk()->json('data.events'), null, 'key');

        foreach (OrderEvents::keys() as $key) {
            $names = array_column($events[$key]['variables'], 'name');

            foreach (PaymentNotices::ORDER_VARIABLES as $name) {
                $this->assertContains($name, $names, "{$key} has no {$name}");
            }
        }

        $problem = $events[PaymentNotices::PROBLEM];
        $this->assertSame(['number', 'order_url', 'problem', 'amount', 'method', 'gateway_ref', 'message'], array_column($problem['variables'], 'name'));
        $this->assertStringContainsString('{{problem}}', $problem['sample']['email']['template']['en'] ?? $problem['sample']['*']['template']['en']);
    }

    #[Test]
    public function a_letter_of_an_order_by_transfer_has_the_link_and_the_details_until_it_is_paid(): void
    {
        $order = $this->transferOrder();
        $channel = $this->channel(OrderEvents::PLACED, ['email' => 'manager@example.com'], "{{payment_method}}\n{{payment_instructions}}\n{{payment_url}}");

        $this->app->make(OrderChannels::class)->send(OrderEvents::PLACED, [$channel->id], OrderData::of($order));

        $values = Delivery::query()->where('channel_id', $channel->id)->sole()->variables;
        $this->assertSame('Bank transfer', $values['payment_method']);
        $this->assertStringContainsString('Shop A Ltd', $values['payment_instructions']);
        $this->assertStringContainsString('/pay/'.$order->number, $values['payment_url']);

        $this->app->make(Payments::class)->markPaid($order, 'panel');
        $paid = $this->app->make(NotificationEvents::class)->extraValues(OrderEvents::PAID, $order->refresh());
        $this->assertSame(['payment_url' => '', 'payment_instructions' => '', 'payment_method' => 'Bank transfer'], $paid);
    }

    #[Test]
    public function a_payment_problem_goes_to_the_channels_of_the_settings_tab(): void
    {
        $channel = $this->channel(PaymentNotices::PROBLEM, ['email' => 'manager@example.com'], '{{problem}} {{number}}');
        $this->app->make(Settings::class)->save([PaymentNotices::SETTING => [$channel->id]]);
        $order = $this->onlineOrder();

        foreach (['ga_1', 'ga_2'] as $ref) {
            $payment = Payment::query()->create([
                'order_id' => $order->id, 'method_key' => 'gateway-a-card', 'type' => 'gateway-a.card', 'amount' => 4999,
                'currency' => 'EUR', 'status' => PaymentStatus::Pending, 'gateway_ref' => $ref, 'source' => 'pay-page',
            ]);
            $this->app->make(Payments::class)->settle($payment, new GatewayStatus(Reported::Paid, $ref, 4999, 'EUR'));
        }

        $row = Delivery::query()->where('event', PaymentNotices::PROBLEM)->sole();
        $this->assertSame(['Paid twice', (string) $order->number, 'ga_2', 'manager@example.com'], [$row->variables['problem'], $row->variables['number'], $row->variables['gateway_ref'], $row->recipient]);
        $this->assertStringEndsWith('/cms/orders/'.$order->id, $row->variables['order_url']);

        // Saved empty, nobody is told — whatever the config says.
        $this->app['config']->set('webx-payments.notices.problem', [$channel->id]);
        $this->app->make(Settings::class)->save([PaymentNotices::SETTING => []]);
        $this->assertSame([], $this->app->make(PaymentNotices::class)->channels());
    }

    #[Test]
    public function the_settings_tab_chooses_the_channels_of_a_problem(): void
    {
        $tree = $this->app->make(ScreenRegistry::class)->tree(Settings::SCREEN);
        $this->assertStringContainsString('"name":"payments.notify.problem"', (string) json_encode($tree));
        $this->assertStringContainsString('"event":"payments.problem"', (string) json_encode($tree));
    }

    /**
     * @param  array<string, string>  $recipient
     */
    private function channel(string $event, array $recipient, string $template): Channel
    {
        return Channel::query()->create([
            'name' => 'Shop by mail',
            'provider' => 'email',
            'event' => $event,
            'options' => ['mode' => 'site'],
            'recipients' => [$recipient],
            'subject' => ['en' => 'Payments'],
            'template' => ['en' => $template],
            'enabled' => true,
        ])->refresh();
    }
}
