<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Illuminate\Http\Client\ConnectionException;
use WebxUi\Payments\ConnectionResult;
use WebxUi\Payments\Contracts\Gateway;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayUnavailable;

/**
 * «Gateway A» (`gateway-a`, spec §21): the base's own fake gateway with three types — `card`,
 * `form` and `unsigned` — so that the base is checked both with the optional abilities of §6.4 and
 * without them, and a satellite sees a model to follow. Its API is `FakeGatewayServer`, inside the
 * process; its key is a visible test one, and the journal wipes it all the same (§20.5).
 *
 * Registered in tests and the playground; on a development site only by
 * `WEBX_PAYMENTS_FAKE_GATEWAY=true` ({@see FakeGatewaySite}), never in production.
 */
readonly class FakeGateway implements Gateway
{
    public const string KEY = 'gateway-a';

    /** The test key: fake, visible, and still wiped from the journal. */
    public const string SECRET = 'fake-secret-key-0001';

    public const string SHOP = 'shop-a';

    public const string API = 'https://'.FakeGatewayServer::HOST;

    public function __construct(private FakeGatewayServer $server) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(string $locale): string
    {
        return 'Gateway A (test)';
    }

    public function icon(): ?string
    {
        return null;
    }

    public function settings(): array
    {
        return [
            ['name' => 'shop_id', 'type' => 'text', 'label' => 'Shop id', 'default' => self::SHOP],
            ['name' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => ['test' => 'Test'], 'default' => 'test'],
        ];
    }

    public function credentials(): array
    {
        return [
            ['name' => 'secret_key', 'type' => 'password', 'label' => 'Secret key', 'secret' => true],
        ];
    }

    public function secretHeaders(): array
    {
        return ['X-Gateway-A-Key'];
    }

    public function secretFields(): array
    {
        return ['access_token'];
    }

    /** @param  array<string, mixed>  $settings */
    public function testConnection(Credentials $credentials, array $settings, GatewayHttp $http): ConnectionResult
    {
        try {
            $token = GatewayAToken::fetch($credentials, $http);
            $answer = $http->request()->withToken($token)->get(self::API.'/v1/account');
        } catch (ConnectionException) {
            return ConnectionResult::failed('The gateway did not answer in time');
        } catch (GatewayUnavailable $e) {
            return ConnectionResult::failed($e->getMessage());
        }

        return $answer->successful()
            ? ConnectionResult::ok('Connected to shop '.self::SHOP)
            : ConnectionResult::failed("The gateway answered with HTTP {$answer->status()}");
    }

    /** The gateway's API, for a test that wants it to fail on purpose. */
    public function server(): FakeGatewayServer
    {
        return $this->server;
    }

    public function failNext(string $failure): void
    {
        $this->server->failNext($failure);
    }
}
