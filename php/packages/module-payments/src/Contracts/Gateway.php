<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

use WebxUi\Payments\ConnectionResult;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;

/**
 * A payment gateway on the site (spec §14.1): who holds the keys and how to test them. Its online
 * types (`ChargesOnline`) carry its key as their provider: `gateway-a` → `gateway-a.card`.
 */
interface Gateway
{
    /** 'gateway-a': lowercase letters, digits and dashes; never 'store'. */
    public function key(): string;

    public function title(string $locale): string;

    public function icon(): ?string;

    /**
     * Non-secret settings of the gateway shared by its methods: shop id, test mode.
     *
     * @return list<array<string, mixed>>
     */
    public function settings(): array;

    /**
     * Secret fields: stored encrypted (§15), never shown back; `multiline: true` for a PEM key.
     *
     * @return list<array<string, mixed>> each with `secret: true`
     */
    public function credentials(): array;

    /**
     * Header names whose values the journal wipes whole, besides `Authorization` and the cookies
     * (§20.5, rule 2): 'X-Token'.
     *
     * @return list<string>
     */
    public function secretHeaders(): array;

    /**
     * Names of json and form fields whose values are secrets made at run time — 'access_token'
     * of an OAuth answer (§20.5, rule 3). Empty for most gateways.
     *
     * @return list<string>
     */
    public function secretFields(): array;

    /**
     * The cheapest authenticated API call; words for the panel on failure, never the raw body.
     *
     * @param  array<string, mixed>  $settings  the gateway's own settings of the method
     */
    public function testConnection(Credentials $credentials, array $settings, GatewayHttp $http): ConnectionResult;
}
