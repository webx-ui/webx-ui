<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Contracts\Gateway;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\Testing\FakeGateway;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\GatewayACard;
use WebxUi\Payments\Testing\GatewayANotCards;

/**
 * `DeclaresNotCardFields` from the gateway to the journal (spec §6.4, §20.5): what the gateway
 * declares reaches the mask of a real call, and a gateway without the contract loses nothing.
 */
final class NotCardFieldsTest extends TestCase
{
    /** A date in milliseconds that passes Luhn. */
    private const string DATE = '1730000000001';

    #[Test]
    public function a_gateway_with_the_contract_keeps_its_dates_in_the_journal(): void
    {
        $event = $this->answerOf(new GatewayANotCards($this->fakeServer()));

        $this->assertSame('{"create_date":'.self::DATE.',"other":*********0001}', $event->response_body);
        $this->assertSame(['pan' => 1], $event->masked);
    }

    #[Test]
    public function a_gateway_without_the_contract_has_them_starred(): void
    {
        $event = $this->answerOf(new FakeGateway($this->fakeServer()));

        $this->assertSame('{"create_date":*********0001,"other":*********0001}', $event->response_body);
        $this->assertSame(['pan' => 2], $event->masked);
    }

    private function answerOf(Gateway $gateway): PaymentGatewayEvent
    {
        Http::fake(['gateway-a.test/*' => Http::response('{"create_date":'.self::DATE.',"other":'.self::DATE.'}')]);

        $this->app->make(Gateways::class)->register($gateway);

        $this->app->make(GatewayCalls::class)
            ->http(new GatewayACard($this->fakeServer()), $this->method(), Credentials::of(['secret_key' => self::SECRET]), ExchangeKind::Status, 'poll')
            ->request()
            ->get('https://gateway-a.test/api/status');

        return PaymentGatewayEvent::query()->sole();
    }

    private function fakeServer(): FakeGatewayServer
    {
        return $this->app->make(FakeGatewayServer::class);
    }

    private function method(): Method
    {
        return new Method(
            key: 'gateway-a-card',
            type: 'gateway-a.card',
            titles: ['en' => 'Gateway A'],
            descriptions: [],
            icon: null,
            enabled: true,
            position: 0,
            deliveryRule: [],
            minAmount: null,
            maxAmount: null,
            settings: [],
            hasCredentials: true,
        );
    }
}
