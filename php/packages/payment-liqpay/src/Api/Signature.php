<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Api;

use InvalidArgumentException;

/**
 * `base64(hash(private + data + private))`, where `data` is the base64 string itself and not the
 * JSON inside it (spec §3.2): re-encoding the JSON would give another string and another signature.
 */
final class Signature
{
    private const string SHA1 = 'sha1';

    private const string SHA3 = 'sha3-256';

    /** Our requests: the version decides the algorithm, any other pair is `invalid_signature` (§3.2). */
    public static function sign(string $data, string $privateKey, int $version): string
    {
        return self::hash(self::algorithm($version), $data, $privateKey);
    }

    /**
     * A notification: either algorithm until a live one shows which LiqPay uses (decision 5). Both
     * hash the private key on both sides, so neither can be forged without it.
     */
    public static function verify(string $data, string $signature, string $privateKey): bool
    {
        return hash_equals(self::hash(self::SHA3, $data, $privateKey), $signature)
            || hash_equals(self::hash(self::SHA1, $data, $privateKey), $signature);
    }

    private static function algorithm(int $version): string
    {
        return match (true) {
            $version === 7 => self::SHA3,
            $version >= 3 && $version <= 6 => self::SHA1,
            default => throw new InvalidArgumentException("LiqPay has no signature for version {$version}."),
        };
    }

    private static function hash(string $algorithm, string $data, string $privateKey): string
    {
        return base64_encode(hash($algorithm, $privateKey.$data.$privateKey, true));
    }
}
