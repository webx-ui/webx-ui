<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Illuminate\Http\Client\ConnectionException;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayUnavailable;

/**
 * The OAuth-like token of gateway A's card API: a derived secret (§20.5, rule 3) — the answer's
 * `access_token` is wiped by `Gateway::secretFields()`, and the bearer of every later request by
 * `GatewayHttp::secret()`.
 */
final class GatewayAToken
{
    /** @throws GatewayUnavailable */
    public static function fetch(Credentials $credentials, GatewayHttp $http): string
    {
        try {
            $answer = $http->kind(ExchangeKind::Key)->request()
                ->withHeaders(['X-Gateway-A-Key' => (string) $credentials->get('secret_key')])
                ->asForm()
                ->post(FakeGateway::API.'/v1/token', ['grant_type' => 'client_credentials', 'shop_id' => FakeGateway::SHOP]);
        } catch (ConnectionException $e) {
            throw GatewayUnavailable::timeout($e);
        }

        if (! $answer->successful()) {
            throw $answer->status() === 401
                ? GatewayUnavailable::because('The gateway does not take the key')
                : GatewayUnavailable::answered($answer->status());
        }

        $token = $answer->json('access_token');

        if (! is_string($token) || $token === '') {
            throw GatewayUnavailable::malformed('token');
        }

        $http->secret($token, 'access_token');

        return $token;
    }
}
