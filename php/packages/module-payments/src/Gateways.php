<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use WebxUi\Payments\Contracts\Gateway;

/**
 * The gateways of the site (spec §6.2, §14.1). A satellite registers its own from `boot()` through
 * `callAfterResolving(Gateways::class)`, next to its types; a class is made on first use.
 */
final class Gateways
{
    /** Lowercase letters, digits and dashes — the provider part of its types' keys. */
    public const string KEY = '/^[a-z0-9-]{1,32}$/';

    /** @var array<string, Gateway> */
    private array $gateways = [];

    /** @var list<class-string<Gateway>> */
    private array $pending = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<Gateway>|Gateway  $gateway */
    public function register(string|Gateway $gateway): void
    {
        if (is_string($gateway)) {
            if (in_array($gateway, $this->pending, true)) {
                throw new LogicException("Payment gateway {$gateway} is registered twice.");
            }

            $this->pending[] = $gateway;

            return;
        }

        $this->add($gateway);
    }

    public function has(string $key): bool
    {
        $this->make();

        return isset($this->gateways[$key]);
    }

    /** @throws UnknownGateway */
    public function get(string $key): Gateway
    {
        $this->make();

        return $this->gateways[$key] ?? throw UnknownGateway::named($key);
    }

    /** @return list<Gateway> */
    public function all(): array
    {
        $this->make();

        return array_values($this->gateways);
    }

    private function make(): void
    {
        while ($this->pending !== []) {
            $class = array_shift($this->pending);
            $gateway = $this->container->make($class);

            if (! $gateway instanceof Gateway) {
                throw new InvalidArgumentException("{$class} is not a payment gateway: it does not implement ".Gateway::class.'.');
            }

            $this->add($gateway);
        }
    }

    private function add(Gateway $gateway): void
    {
        $key = $gateway->key();

        if (preg_match(self::KEY, $key) !== 1 || $key === 'store') {
            throw new InvalidArgumentException("[{$key}] is not a gateway key: lowercase letters, digits and dashes, at most 32, never «store».");
        }

        if (isset($this->gateways[$key])) {
            throw new LogicException("Payment gateway {$key} is registered twice.");
        }

        $this->gateways[$key] = $gateway;
    }
}
