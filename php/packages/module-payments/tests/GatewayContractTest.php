<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\Contracts\Gateway;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Reported;
use WebxUi\Payments\StartRequest;
use WebxUi\Payments\Testing\FakeGateway;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\GatewayACard;
use WebxUi\Payments\Testing\GatewayAForm;
use WebxUi\Payments\Testing\GatewayAType;
use WebxUi\Payments\Testing\GatewayAUnsigned;
use WebxUi\Payments\Testing\GatewayContract;

/**
 * The contract of a satellite (spec §21.3) on the base's own «gateway A»: three types, so that the
 * base is checked with the optional abilities of §6.4 and without them — `card` (cancel, hold
 * with a partial capture, e-mail and lines), `form` (an approval to complete, a signed answer) and
 * `unsigned` (notifications not trusted, a phone).
 */
final class GatewayContractTest extends TestCase
{
    use GatewayContract;

    /** A type that went past GatewayHttp, for the test that the contract catches it. */
    private ?ChargesOnline $rogue = null;

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-payments.fake_gateway', true);
    }

    protected function gateway(): Gateway
    {
        return $this->app->make(Gateways::class)->get(FakeGateway::KEY);
    }

    protected function methodTypes(): array
    {
        if ($this->rogue !== null) {
            return [$this->rogue];
        }

        return [
            $this->app->make(GatewayACard::class),
            $this->app->make(GatewayAForm::class),
            $this->app->make(GatewayAUnsigned::class),
        ];
    }

    protected function credentials(ChargesOnline $type): Credentials
    {
        return Credentials::of(['secret_key' => FakeGateway::SECRET]);
    }

    protected function answerStatus(ChargesOnline $type, Payment $payment, string $gatewayStatus): void
    {
        $this->server()->open($payment->uuid, $this->kindOf($type), $payment->amount, $payment->currency, $gatewayStatus, [
            'captured' => $gatewayStatus === 'captured' ? $payment->amount : null,
            'paid_at' => '2026-11-03T10:00:00+00:00',
        ]);
    }

    protected function answerAuthorized(ChargesOnline $type, Payment $payment): void
    {
        $this->server()->open($payment->uuid, 'card', $payment->amount, $payment->currency, 'authorized', [
            'capture' => false,
            'authorized' => $payment->amount,
        ]);
    }

    protected function answerApproved(ChargesOnline $type, Payment $payment): void
    {
        $this->server()->open($payment->uuid, 'form', $payment->amount, $payment->currency, 'approved');
    }

    protected function charges(ChargesOnline $type, Payment $payment): ?int
    {
        $state = $this->server()->payment($payment->uuid);

        return $state === null ? null : (int) $state['charges'];
    }

    protected function statusTable(ChargesOnline $type): array
    {
        $this->assertInstanceOf(GatewayAType::class, $type);

        return $type->statuses();
    }

    protected function notification(ChargesOnline $type, Payment $payment, string $variant): ?Request
    {
        if ($variant === 'unrelated' && $this->kindOf($type) !== 'card') {
            return null;
        }

        return $this->server()->notification($payment->uuid, $variant);
    }

    protected function answerError(ChargesOnline $type, Payment $payment): string
    {
        $this->server()->failNext(FakeGatewayServer::SERVER_ERROR);

        return FakeGatewayServer::ERROR_BODY;
    }

    protected function requestsMade(): int
    {
        return $this->server()->served();
    }

    /** The answers gateway A waits for: `{"received":true}`, a signed `accept`, a plain «OK». */
    protected function checkAnswers(ChargesOnline $type, Response $accept, Response $reject): void
    {
        match ($this->kindOf($type)) {
            'card' => $this->assertSame(['{"received":true}', 400], [$accept->getContent(), $reject->getStatusCode()]),
            'form' => (function () use ($accept, $reject): void {
                $body = json_decode((string) $accept->getContent(), true);
                $this->assertIsArray($body);
                $this->assertSame(FakeGatewayServer::sign($body['data']), $body['signature'], 'The answer to a form notification is signed.');
                $this->assertSame('accept', json_decode((string) base64_decode($body['data']), true)['status'] ?? null);
                $this->assertSame(400, $reject->getStatusCode());
            })(),
            default => $this->assertSame(['OK', 'ERROR'], [$accept->getContent(), $reject->getContent()]),
        };
    }

    #[Test]
    public function a_request_past_gateway_http_fails_the_contract(): void
    {
        $this->rogue = new class($this->server()) extends GatewayACard
        {
            public function start(Method $method, Credentials $credentials, StartRequest $request, GatewayHttp $http): Handoff
            {
                // The satellite's own client: no journal, no in-process gateway — the network.
                Http::post(FakeGateway::API.'/v1/token', ['grant_type' => 'client_credentials']);

                return parent::start($method, $credentials, $request, $http);
            }
        };

        $caught = null;

        try {
            $this->every_request_goes_through_gateway_http_and_no_key_reaches_the_journal();
        } catch (StrayRequestException|AssertionFailedError $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(StrayRequestException::class, $caught, 'A request past GatewayHttp passed the contract.');
    }

    #[Test]
    public function the_fake_keeps_its_own_statuses_apart(): void
    {
        // «Completed» of the form type is not «paid» until the shop completes it (§6.4).
        $this->assertSame(Reported::Pending, $this->app->make(GatewayAForm::class)->statuses()['approved']);
    }

    private function server(): FakeGatewayServer
    {
        return $this->app->make(FakeGatewayServer::class);
    }

    private function kindOf(ChargesOnline $type): string
    {
        return substr($type->key(), strlen(FakeGateway::KEY) + 1);
    }
}
