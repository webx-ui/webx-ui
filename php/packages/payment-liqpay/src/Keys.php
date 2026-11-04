<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay;

use WebxUi\Payments\Credentials;
use WebxUi\Payments\Method;

/**
 * The pair of keys of a method's mode (spec §13.2). Only that mode's: a notification signed with
 * the test key must not pay an order on a live method (§8.1).
 *
 * The private key stays inside `Credentials`, which turns into `[hidden]` in a dump or a log line.
 */
final readonly class Keys
{
    private function __construct(
        public string $mode,
        public ?string $publicKey,
        private Credentials $credentials,
    ) {}

    /** Anything but `live` is `test`: a broken setting must not end up taking real money. */
    public static function of(Method $method, Credentials $credentials): self
    {
        $mode = ($method->settings['mode'] ?? null) === 'live' ? 'live' : 'test';
        $public = $method->settings[$mode.'_public_key'] ?? null;

        return new self($mode, is_string($public) && $public !== '' ? $public : null, $credentials);
    }

    public function privateKey(): ?string
    {
        return $this->credentials->get($this->mode.'_private_key');
    }
}
