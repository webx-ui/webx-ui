<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Preview;

use JsonException;

/**
 * The signature on a preview link.
 *
 * One opaque string rather than Laravel's `expires` and `signature` pair, because the link is
 * handed around as a whole — to an `<iframe>`, to a phone, to an agent through `preview_url` —
 * and a single parameter survives that better than two. Inside: which entity, for whom, until
 * when; signed with the application key, so a token made for one page opens no other, and one
 * that has expired opens nothing.
 */
final class PreviewToken
{
    private const ALGORITHM = 'sha256';

    public function __construct(private readonly string $key) {}

    public function make(string $type, string $id, ?int $adminId, int $expires): string
    {
        $payload = $this->encode(json_encode([$type, $id, $adminId, $expires], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->sign($payload);
    }

    /**
     * The grant a token carries, if it is genuine, unexpired and for this very entity.
     */
    public function verify(string $token, string $type, string $id, ?int $now = null): ?PreviewGrant
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if ($payload === '' || $signature === '' || ! hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        try {
            $claims = json_decode($this->decode($payload), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($claims) || count($claims) !== 4) {
            return null;
        }

        [$forType, $forId, $adminId, $expires] = $claims;

        if ($forType !== $type || (string) $forId !== $id || ! is_int($expires)) {
            return null;
        }

        if ($expires <= ($now ?? time())) {
            return null;
        }

        return new PreviewGrant($type, $id, is_int($adminId) ? $adminId : null, $expires);
    }

    private function sign(string $payload): string
    {
        return hash_hmac(self::ALGORITHM, $payload, $this->key);
    }

    private function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function decode(string $encoded): string
    {
        return (string) base64_decode(strtr($encoded, '-_', '+/'), true);
    }
}
