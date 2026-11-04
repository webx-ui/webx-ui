<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay;

use Illuminate\Support\Str;
use WebxUi\Payment\LiqPay\Api\Client;
use WebxUi\Payments\ConnectionResult;
use WebxUi\Payments\Contracts\DeclaresNotCardFields;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayUnavailable;

/**
 * LiqPay as the base sees it (spec §5): who holds the keys and what the journal must know.
 *
 * The public key is a setting and not a credential on purpose (§13.1): it travels inside `data`, and
 * the journal wipes credentials even inside base64, which would break the string a signature covers.
 */
final class LiqPayGateway implements DeclaresNotCardFields
{
    public const string KEY = 'liqpay';

    /** How long a payment lives when the method does not say (decision 13). */
    public const int EXPIRES_MINUTES = 60;

    public static function word(string $key, ?string $locale = null): string
    {
        return (string) __(self::line($key), [], $locale);
    }

    /** A key of the dictionary as the base's form takes a label: it translates it itself. */
    public static function line(string $key): string
    {
        return 'webx-payment-liqpay::liqpay.'.$key;
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function title(string $locale): string
    {
        return self::word('gateway.title', $locale);
    }

    /** The mark from the package's own assets: a data URL, nothing to publish or route. */
    public function icon(): ?string
    {
        $svg = @file_get_contents(__DIR__.'/../resources/icons/liqpay.svg');

        return $svg === false ? null : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function settings(): array
    {
        return [
            [
                'name' => 'mode',
                'type' => 'select',
                'label' => self::line('gateway.mode'),
                'help' => self::line('gateway.mode_help'),
                'options' => ['test' => self::line('gateway.modes.test'), 'live' => self::line('gateway.modes.live')],
                'default' => 'test',
            ],
            ['name' => 'test_public_key', 'type' => 'text', 'label' => self::line('gateway.test_public_key'), 'help' => self::line('gateway.public_key_help')],
            ['name' => 'live_public_key', 'type' => 'text', 'label' => self::line('gateway.live_public_key'), 'help' => self::line('gateway.public_key_help')],
            [
                'name' => 'language',
                'type' => 'select',
                'label' => self::line('gateway.language'),
                'help' => self::line('gateway.language_help'),
                'options' => [
                    'auto' => self::line('gateway.languages.auto'),
                    'uk' => self::line('gateway.languages.uk'),
                    'en' => self::line('gateway.languages.en'),
                ],
                'default' => 'auto',
            ],
            [
                'name' => 'expires_minutes',
                'type' => 'number',
                'label' => self::line('gateway.expires_minutes'),
                'help' => self::line('gateway.expires_minutes_help'),
                'default' => self::EXPIRES_MINUTES,
            ],
        ];
    }

    public function credentials(): array
    {
        return [
            ['name' => 'test_private_key', 'type' => 'password', 'label' => self::line('gateway.test_private_key'), 'secret' => true],
            ['name' => 'live_private_key', 'type' => 'password', 'label' => self::line('gateway.live_private_key'), 'secret' => true],
        ];
    }

    /** The signature is in the body: no header carries a secret. */
    public function secretHeaders(): array
    {
        return [];
    }

    /** Nothing secret is made at run time: the private key only ever goes into a hash. */
    public function secretFields(): array
    {
        return [];
    }

    /** Dates in milliseconds and payment numbers: long, and some pass Luhn by chance (§12). */
    public function notCardFields(): array
    {
        return [
            'create_date',
            'end_date',
            'refund_date_last',
            'reserve_date',
            'completion_date',
            'payment_id',
            'prev_payment_id',
            'reserve_payment_id',
            'transaction_id',
        ];
    }

    /**
     * The status of a payment nobody has (§5.3): «not found» is the one answer that needs both keys
     * to be right, and it costs nothing. The body of an answer never reaches the panel.
     *
     * @param  array<string, mixed>  $settings
     */
    public function testConnection(Credentials $credentials, array $settings, GatewayHttp $http): ConnectionResult
    {
        $keys = Keys::from($settings, $credentials);

        if (! $keys->complete()) {
            return ConnectionResult::failed(self::word('connection.no_keys'));
        }

        if (! $keys->fitTheMode()) {
            return ConnectionResult::failed(self::word('connection.wrong_mode'));
        }

        try {
            $code = Client::for($http, $keys)->call('status', ['order_id' => (string) Str::uuid()])->errorCode();
        } catch (GatewayUnavailable) {
            return ConnectionResult::failed(self::word('connection.no_answer'));
        }

        return match ($code) {
            'payment_not_found' => ConnectionResult::ok(self::word('connection.ok')),
            'invalid_signature', 'err_signature' => ConnectionResult::failed(self::word('connection.private_key')),
            'public_key_not_found' => ConnectionResult::failed(self::word('connection.public_key')),
            default => ConnectionResult::failed(trim(self::word('connection.no_answer').' '.$code)),
        };
    }
}
