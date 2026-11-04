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
    private const string SANDBOX = 'sandbox_';

    private function __construct(
        public string $mode,
        public ?string $publicKey,
        private Credentials $credentials,
    ) {}

    public static function of(Method $method, Credentials $credentials): self
    {
        return self::from($method->settings, $credentials);
    }

    /**
     * Anything but `live` is `test`: a broken setting must not end up taking real money.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function from(array $settings, Credentials $credentials): self
    {
        $mode = ($settings['mode'] ?? null) === 'live' ? 'live' : 'test';
        $public = $settings[$mode.'_public_key'] ?? null;

        return new self($mode, is_string($public) && $public !== '' ? $public : null, $credentials);
    }

    public function privateKey(): ?string
    {
        return $this->credentials->get($this->mode.'_private_key');
    }

    public function complete(): bool
    {
        return $this->publicKey !== null && $this->privateKey() !== null;
    }

    /** Live keys in the test mode would be real money where a manager does not expect it (§5.3). */
    public function fitTheMode(): bool
    {
        $sandbox = $this->mode === 'test';

        return str_starts_with((string) $this->publicKey, self::SANDBOX) === $sandbox
            && str_starts_with((string) $this->privateKey(), self::SANDBOX) === $sandbox;
    }
}
