<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use WebxUi\Payments\Credentials;
use WebxUi\Payments\Method;

/** The recorded answers (spec §17.1) and notifications made from them with the fake keys. */
trait Recorded
{
    protected const string PUBLIC_KEY = 'sandbox_FAKEpublic00000000';

    protected const string PRIVATE_KEY = 'sandbox_FAKEprivate0000000000000000000000';

    /** @return array<string, mixed> */
    protected static function fixture(string $name): array
    {
        $file = __DIR__.'/Fixtures/'.$name.'.json';

        return json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The `data` of a recorded notification with some of its fields changed.
     *
     * @param  array<string, mixed>  $changes  a null value takes the field away
     */
    protected static function dataOf(string $notification, array $changes = []): string
    {
        $fields = array_filter([...self::fixture('notify/'.$notification)['fields'], ...$changes], static fn (mixed $value): bool => $value !== null);

        return base64_encode((string) json_encode($fields, JSON_UNESCAPED_SLASHES));
    }

    protected static function signed(string $data, string $algorithm = 'sha3-256', string $key = self::PRIVATE_KEY): string
    {
        return base64_encode(hash($algorithm, $key.$data.$key, true));
    }

    /** @param  array<string, mixed>  $settings */
    protected static function liqpayMethod(array $settings = []): Method
    {
        return new Method(
            key: 'liqpay-checkout',
            type: 'liqpay.checkout',
            titles: ['en' => 'LiqPay'],
            descriptions: [],
            icon: null,
            enabled: true,
            position: 0,
            deliveryRule: ['mode' => 'any'],
            minAmount: null,
            maxAmount: null,
            settings: ['mode' => 'test', 'test_public_key' => self::PUBLIC_KEY, ...$settings],
            hasCredentials: true,
        );
    }

    protected static function testKey(): Credentials
    {
        return Credentials::of(['test_private_key' => self::PRIVATE_KEY]);
    }
}
