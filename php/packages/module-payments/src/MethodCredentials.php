<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use WebxUi\Payments\Contracts\Gateway;
use WebxUi\Payments\Models\PaymentMethod;

/**
 * The gateway's keys of one method (spec §15.1): the method's encrypted column, and over it the
 * site's environment — `WEBX_PAYMENTS_<METHOD>_<FIELD>` (`WEBX_PAYMENTS_GATEWAY_A_CARD_SECRET_KEY`),
 * stronger than the column, for a shop that keeps live keys out of the database (decision 21).
 *
 * Read only when a gateway is called: the list of methods knows only whether keys are there.
 */
final readonly class MethodCredentials
{
    public function __construct(
        private MethodTypes $types,
        private Gateways $gateways,
    ) {}

    public function for(Method $method): Credentials
    {
        $values = [];

        $row = PaymentMethod::query()->where('key', $method->key)->first();

        if ($row !== null && $row->getRawOriginal('credentials') !== null) {
            try {
                // The column's cast, by hand: a key it cannot read is a case, not a crash.
                $decoded = json_decode(Crypt::decryptString((string) $row->getRawOriginal('credentials')), true);
                $values = is_array($decoded) ? $decoded : [];
            } catch (DecryptException) {
                // A new APP_KEY: the stored keys are unreadable, the method has none (§15.1).
                Log::warning('webx-payments: the keys of a method cannot be decrypted, enter them again', ['method' => $method->key]);
            }
        }

        foreach ($this->environment($method) as $name => $value) {
            $values[$name] = $value;
        }

        return Credentials::of($values);
    }

    /** Whether the environment sets any key of the method — the list then offers it without the column. */
    public function inEnvironment(Method $method): bool
    {
        return $this->environment($method) !== [];
    }

    /** `WEBX_PAYMENTS_GATEWAY_A_CARD_SECRET_KEY` for the field `secret_key` of the method `gateway-a-card`. */
    public static function variable(string $methodKey, string $field): string
    {
        $part = static fn (string $text): string => strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $text), '_'));

        return 'WEBX_PAYMENTS_'.$part($methodKey).'_'.$part($field);
    }

    /** @return array<string, string> */
    private function environment(Method $method): array
    {
        $gateway = $this->gateway($method);

        if ($gateway === null) {
            return [];
        }

        $values = [];

        foreach ($gateway->credentials() as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name)) {
                continue;
            }

            $value = Env::get(self::variable($method->key, $name));

            if (is_string($value) && $value !== '') {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    private function gateway(Method $method): ?Gateway
    {
        if (! $this->types->has($method->type)) {
            return null;
        }

        $provider = $this->types->get($method->type)->provider();

        return $this->gateways->has($provider) ? $this->gateways->get($provider) : null;
    }
}
