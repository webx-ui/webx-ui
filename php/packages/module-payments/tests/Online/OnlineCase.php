<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Tests\Checkout\PaymentsCheckoutCase;

/**
 * An order of the checkout paid online with «gateway A» (spec §8.2, §21): the fake gateway on,
 * its three methods with the test key, an order of 49,99 € that awaits payment by one of them.
 */
abstract class OnlineCase extends PaymentsCheckoutCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        FakeGatewaySite::methods();
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-payments.fake_gateway', true);
    }

    /** An order placed with a method of gateway A: 'card', 'form' or 'unsigned'. */
    protected function onlineOrder(string $kind = 'card'): Order
    {
        return $this->place($this->cart(), [
            'awaitPayment' => true,
            'blocks' => ['payment' => [
                'method' => 'gateway-a-'.$kind,
                'type' => 'gateway-a.'.$kind,
                'kind' => 'online',
                'title' => ['en' => 'Gateway A'],
                'price' => null,
            ]],
        ]);
    }

    protected function server(): FakeGatewayServer
    {
        return $this->app->make(FakeGatewayServer::class);
    }

    protected function payPage(Order $order): string
    {
        return $this->app->make(PaymentLinks::class)->page($order);
    }

    /** @return list<string> «direction/kind» of every event, in order */
    protected function events(): array
    {
        return PaymentGatewayEvent::query()->orderBy('id')->get()
            ->map(static fn (PaymentGatewayEvent $e): string => $e->direction.'/'.$e->kind)
            ->all();
    }
}
