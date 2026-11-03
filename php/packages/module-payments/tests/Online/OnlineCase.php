<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Models\Payment;
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

    /**
     * An order placed with a method of gateway A: 'card', 'form' or 'unsigned'.
     *
     * @param  list<array<string, mixed>>|null  $items  the cart's lines; null — one «Item A» of 49,99 €
     */
    protected function onlineOrder(string $kind = 'card', ?array $items = null): Order
    {
        return $this->place($items !== null ? $this->cart($items) : $this->cart(), [
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

    /**
     * An attempt of a new order the buyer took to gateway A by the page's «Pay» and `/pay/go`;
     * the `card` payment is open at the gateway, the others are opened by hand as the page would.
     *
     * @param  list<array<string, mixed>>|null  $items  the cart's lines; null — one «Item A»
     * @return array{0: Order, 1: Payment}
     */
    protected function attemptAtGateway(string $kind = 'card', bool $hold = false, ?array $items = null): array
    {
        if ($hold) {
            $this->app->make('db')->table('payment_methods')->where('key', 'gateway-a-card')->update(['settings' => json_encode(['hold' => true])]);
        }

        $order = $this->onlineOrder($kind, $items);
        $this->post($this->app->make(PaymentLinks::class)->attempts($order));
        $payment = Payment::query()->latest('id')->firstOrFail();
        $this->get($this->app->make(PaymentLinks::class)->go($payment));

        if ($kind !== 'card') {
            $this->server()->open($payment->uuid, $kind, 4999, 'EUR', $kind === 'form' ? 'wait' : 'CREATED', ['return_url' => $this->app->make(PaymentLinks::class)->returnUrl($payment)]);
        }

        return [$order, $payment->refresh()];
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
