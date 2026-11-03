<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Exchange\ExchangeConnection;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\Models\PaymentGatewayEvent;

/**
 * Every request of a satellite through `GatewayHttp` is an event of the journal (spec §20.3): the
 * request as sent, the answer as it came or the transport error, with the keys masked.
 */
final class GatewayHttpTest extends TestCase
{
    #[Test]
    public function a_request_and_its_answer_are_one_event(): void
    {
        Http::fake(['gateway-a.test/*' => Http::response('{"status":"paid","echo":"'.self::SECRET.'"}', 200, ['Content-Type' => 'application/json'])]);

        $response = $this->http(ExchangeKind::Status)->request()
            ->withHeaders(['Authorization' => 'Bearer '.self::SECRET])
            ->asForm()
            ->post('https://gateway-a.test/api/status', ['order_id' => '01JC0000000000000000000000', 'private_key' => self::SECRET]);

        // The satellite reads the answer as if no journal stood in between.
        $this->assertSame('paid', $response->json('status'));

        $event = PaymentGatewayEvent::query()->sole();
        $this->assertSame('out', $event->direction);
        $this->assertSame('status', $event->kind);
        $this->assertSame('gateway-a', $event->gateway);
        $this->assertSame('gateway-a-card', $event->method_key);
        $this->assertSame(11, $event->payment_id);
        $this->assertSame(5, $event->order_id);
        $this->assertSame('poll', $event->source);
        $this->assertSame('POST', $event->http_method);
        $this->assertSame('https://gateway-a.test/api/status', $event->url);
        $this->assertSame('order_id=01JC0000000000000000000000&private_key=[secret:secret_key]', $event->request_body);
        $this->assertSame(['[secret:header]'], $event->request_headers['Authorization'] ?? null);
        $this->assertSame(200, $event->response_status);
        $this->assertSame('{"status":"paid","echo":"[secret:secret_key]"}', $event->response_body);
        $this->assertNull($event->transport_error);
        $this->assertNotNull($event->duration_ms);
        $this->assertNull($event->outcome, 'An outgoing request has no decision: the answer is its outcome.');
    }

    #[Test]
    public function a_gateway_error_is_an_event_too(): void
    {
        Http::fake(['gateway-a.test/*' => Http::response('Internal error', 500)]);

        $response = $this->http(ExchangeKind::Start)->request()->post('https://gateway-a.test/api/create', ['amount' => '120.00']);

        $this->assertSame(500, $response->status());
        $event = PaymentGatewayEvent::query()->sole();
        $this->assertSame('start', $event->kind);
        $this->assertSame(500, $event->response_status);
        $this->assertSame('Internal error', $event->response_body);
    }

    #[Test]
    public function a_timeout_is_an_event_without_an_answer(): void
    {
        Http::fake(['gateway-a.test/*' => Http::failedConnection('cURL error 28: Operation timed out after 15001 milliseconds for https://gateway-a.test/api/status?key='.self::SECRET)]);

        try {
            $this->http(ExchangeKind::Status)->request()->get('https://gateway-a.test/api/status');
            $this->fail('The connection error reaches the satellite.');
        } catch (ConnectionException) {
        }

        $event = PaymentGatewayEvent::query()->sole();
        $this->assertNull($event->response_status);
        $this->assertNull($event->response_body);
        $this->assertStringContainsString('Operation timed out', (string) $event->transport_error);
        $this->assertStringContainsString('[secret:secret_key]', (string) $event->transport_error);
        $this->assertStringNotContainsString(self::SECRET, (string) $event->transport_error);
    }

    #[Test]
    public function a_failed_write_of_the_journal_never_fails_the_request(): void
    {
        $log = Log::spy();
        $this->app['config']->set('webx-payments.exchange.connection', 'nowhere');
        $this->app['config']->set('database.connections.nowhere', ['driver' => 'sqlite', 'database' => '/nonexistent/dir/db.sqlite']);
        $this->app->forgetInstance(ExchangeConnection::class);
        $this->app->forgetInstance(ExchangeJournal::class);
        Http::fake(['gateway-a.test/*' => Http::response('{"status":"paid"}')]);

        $response = $this->http(ExchangeKind::Status)->request()->get('https://gateway-a.test/api/status');

        $this->assertSame('paid', $response->json('status'));
        $log->shouldHaveReceived('error')->withArgs(static fn (string $message): bool => $message === 'webx-payments: journal write failed')->once();
    }

    #[Test]
    public function a_secret_made_on_the_way_is_masked_in_the_later_events_of_the_trace(): void
    {
        Http::fake([
            'gateway-a.test/oauth' => Http::response('{"access_token":"oauth-token-of-gateway-a-0001"}'),
            'gateway-a.test/api/*' => Http::response('{"ok":true}'),
        ]);
        $http = $this->http(ExchangeKind::Key, fields: ['access_token']);

        $token = (string) $http->request()->post('https://gateway-a.test/oauth')->json('access_token');
        $http->kind(ExchangeKind::Start)->request()->withToken($token)->post('https://gateway-a.test/api/create', ['token_echo' => $token]);

        [$key, $start] = PaymentGatewayEvent::query()->orderBy('id')->get()->all();
        $this->assertSame('key', $key->kind);
        $this->assertSame('{"access_token":"[secret:access_token]"}', $key->response_body);
        $this->assertSame('start', $start->kind);
        $this->assertSame($key->trace, $start->trace);
        $this->assertSame('{"token_echo":"[secret:access_token]"}', $start->request_body);
        $this->assertSame(['[secret:header]'], $start->request_headers['Authorization'] ?? null);
    }

    #[Test]
    public function the_psr_client_for_an_sdk_is_journaled_the_same_way(): void
    {
        Http::fake(['gateway-a.test/*' => Http::response('pong', 200)]);

        $response = $this->http(ExchangeKind::Connection)->psr()->sendRequest(new PsrRequest('GET', 'https://gateway-a.test/ping?key='.self::SECRET));

        $this->assertSame('pong', (string) $response->getBody());
        $event = PaymentGatewayEvent::query()->sole();
        $this->assertSame('connection', $event->kind);
        $this->assertSame('https://gateway-a.test/ping?key=[secret:secret_key]', $event->url);
        $this->assertSame('pong', $event->response_body);
    }

    #[Test]
    public function every_attempt_of_a_retried_request_is_its_own_event(): void
    {
        Http::fake(['gateway-a.test/*' => Http::sequence()->push('busy', 503)->push('{"ok":true}', 200)]);

        $this->http(ExchangeKind::Status)->request()->retry(2, 0, throw: false)->get('https://gateway-a.test/api/status');

        $this->assertSame([503, 200], PaymentGatewayEvent::query()->orderBy('id')->pluck('response_status')->all());
    }

    /** @param  list<string>  $fields */
    private function http(ExchangeKind $kind, array $fields = []): GatewayHttp
    {
        return new GatewayHttp(
            $this->app->make(ExchangeJournal::class),
            $this->app['config'],
            new ExchangeContext(gateway: 'gateway-a', methodKey: 'gateway-a-card', paymentId: 11, orderId: 5, source: 'poll'),
            $fields === [] ? $this->secrets() : $this->secrets(fields: $fields),
            $kind,
        );
    }
}
